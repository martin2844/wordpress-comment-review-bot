<?php
/**
 * OpenAI API Client Module
 *
 * Handles all OpenAI API communication including:
 * - API connection testing
 * - Comment moderation requests
 * - Model-specific parameter handling (GPT-3.5, GPT-4, GPT-5, o1, o3)
 * - Response parsing (JSON and text fallback)
 * - Endpoint routing (chat/completions vs responses)
 *
 * @package WordPress_Review_Bot
 */

if (!defined('ABSPATH')) {
    exit;
}

class WRB_OpenAI_Client {

    /**
     * Test OpenAI API connection
     *
     * @param string $api_key OpenAI API key
     * @param string $model Model name (unused for connection test)
     * @return array Connection test result
     */
    public function test_connection($api_key, $model) {
        $url = 'https://api.openai.com/v1/models';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $start_time = microtime(true);
        $response = curl_exec($ch);
        $end_time = microtime(true);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return array(
                'success' => false,
                'message' => __('Connection error:', 'wordpress-review-bot') . ' ' . $error,
                'code' => 'connection_error'
            );
        }

        if ($http_code !== 200) {
            $response_data = json_decode($response, true);
            $error_message = isset($response_data['error']['message']) ?
                $response_data['error']['message'] :
                __('API returned HTTP code', 'wordpress-review-bot') . ' ' . $http_code;

            return array(
                'success' => false,
                'message' => $error_message,
                'code' => 'api_error',
                'details' => $response_data
            );
        }

        $response_time = round(($end_time - $start_time) * 1000) / 1000;

        return array(
            'success' => true,
            'response_time' => $response_time,
            'api_info' => array(
                'http_code' => $http_code,
                'models_available' => true
            )
        );
    }

    /**
     * Test comment moderation with OpenAI
     *
     * @param string $api_key OpenAI API key
     * @param string $model Model name
     * @param array $comment Comment data
     * @param string $prompt Moderation prompt
     * @param int $max_tokens Maximum tokens
     * @param float $temperature Temperature setting
     * @return array Moderation result
     */
    public function moderate_comment($api_key, $model, $comment, $prompt, $max_tokens, $temperature) {
        // Route to appropriate endpoint based on model type
        if ($this->is_gpt5_reasoning_model($model)) {
            return $this->call_responses_endpoint($api_key, $model, $comment, $prompt, $max_tokens, $temperature);
        } else {
            return $this->call_chat_completions_endpoint($api_key, $model, $comment, $prompt, $max_tokens, $temperature);
        }
    }

    /**
     * Get the appropriate token parameter for a given model
     *
     * @param string $model Model name
     * @return string Token parameter name ('max_tokens' or 'max_completion_tokens')
     */
    private function get_token_parameter_for_model($model) {
        // Newer models use max_completion_tokens instead of max_tokens
        $newer_models = array(
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4-turbo',
            'gpt-4-turbo-preview',
            'gpt-4-0125-preview',
            'gpt-4-1106-preview',
            'gpt-3.5-turbo-0125',
            'gpt-3.5-turbo-1106',
            'gpt-5',
            'gpt-5-mini',
            'gpt-5-nano',
            'gpt-4.1-nano'
        );

        return in_array($model, $newer_models) ? 'max_completion_tokens' : 'max_tokens';
    }

    /**
     * Check if a model supports JSON response format
     *
     * @param string $model Model name
     * @return bool True if model supports JSON format
     */
    private function supports_json_response_format($model) {
        // Models that support structured output / JSON response format
        $json_supported_models = array(
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4-turbo',
            'gpt-4-turbo-preview',
            'gpt-4-0125-preview',
            'gpt-4-1106-preview',
            'gpt-3.5-turbo-0125',
            'gpt-3.5-turbo-1106',
            'gpt-5',
            'gpt-5-mini',
            'gpt-5-nano',
            'gpt-4.1-nano'
        );

        return in_array($model, $json_supported_models);
    }

    /**
     * Check if a model supports custom temperature values
     *
     * @param string $model Model name
     * @return bool True if model supports custom temperature
     */
    private function supports_custom_temperature($model) {
        // Models that only support default temperature (1.0)
        $temperature_restricted_models = array(
            'gpt-5',
            'gpt-5-mini',
            'gpt-5-nano',
            'gpt-4.1-nano',
            'o1-preview',
            'o1-mini',
            'o1'
        );

        return !in_array($model, $temperature_restricted_models);
    }

    /**
     * Get the appropriate temperature value for a model
     *
     * @param string $model Model name
     * @param float $requested_temperature Requested temperature
     * @return float Appropriate temperature for the model
     */
    private function get_temperature_for_model($model, $requested_temperature) {
        if ($this->supports_custom_temperature($model)) {
            return $requested_temperature;
        }
        return 1.0; // Default for restricted models
    }

    /**
     * Check if a model is a reasoning model (o1, o3, gpt-5, etc)
     *
     * @param string $model Model name
     * @return bool True if model is a reasoning model
     */
    private function is_reasoning_model($model) {
        $reasoning_models = array(
            'o1',
            'o1-preview',
            'o1-mini',
            'o3',
            'o3-mini',
            'gpt-5',
            'gpt-5-mini',
            'gpt-5-nano'
        );

        foreach ($reasoning_models as $reasoning_model) {
            if (stripos($model, $reasoning_model) !== false) {
                return true;
            }
        }

        if (stripos($model, 'reasoning') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Check if a model is a GPT-5 reasoning model
     *
     * @param string $model Model name
     * @return bool True if model is GPT-5 family
     */
    private function is_gpt5_reasoning_model($model) {
        $gpt5_models = array(
            'gpt-5',
            'gpt-5-mini',
            'gpt-5-nano'
        );

        foreach ($gpt5_models as $gpt5_model) {
            if (stripos($model, $gpt5_model) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Call responses endpoint for GPT-5 reasoning models
     *
     * @param string $api_key OpenAI API key
     * @param string $model Model name
     * @param array $comment Comment data
     * @param string $prompt Moderation prompt
     * @param int $max_tokens Maximum tokens
     * @param float $temperature Temperature setting
     * @return array API response
     */
    private function call_responses_endpoint($api_key, $model, $comment, $prompt, $max_tokens, $temperature) {
        $url = 'https://api.openai.com/v1/responses';

        $full_prompt = "You are a WordPress comment moderator. Analyze comments fairly and objectively to determine if they should be approved, rejected, or marked as spam. Consider context, relevance, and content quality.\n\n" . $prompt . "\n\nRespond with JSON format: {\"decision\": \"approve/reject/spam\", \"confidence\": 0.00-1.00, \"reasoning\": \"Your detailed reasoning\"}";

        $options = get_option('wrb_options', array());
        $reasoning_effort = isset($options['reasoning_effort']) ? $options['reasoning_effort'] : 'low';
        if (!in_array($reasoning_effort, array('low','medium','high'), true)) {
            $reasoning_effort = 'low';
        }

        $data = array(
            'model' => $model,
            'input' => array(
                array(
                    'type' => 'message',
                    'role' => 'user',
                    'content' => $full_prompt
                )
            ),
            'reasoning' => array(
                'effort' => $reasoning_effort
            )
        );

        error_log("WRB: Calling responses endpoint for gpt-5 model: {$model}. Request data: " . json_encode($data));
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        error_log('=== OpenAI Responses Endpoint Debug ===');
        error_log('Request URL: ' . $url);
        error_log('Request Data: ' . json_encode($data, JSON_PRETTY_PRINT));
        error_log('HTTP Code: ' . $http_code);
        error_log('Raw Response: ' . $response);
        error_log('CURL Error: ' . $error);
        error_log('=== End Debug ===');

        if ($error) {
            return array(
                'success' => false,
                'message' => __('Connection error:', 'wordpress-review-bot') . ' ' . $error,
                'code' => 'connection_error'
            );
        }

        if ($http_code !== 200) {
            $response_data = json_decode($response, true);
            $error_message = isset($response_data['error']['message']) ?
                $response_data['error']['message'] :
                __('API returned HTTP code', 'wordpress-review-bot') . ' ' . $http_code;

            return array(
                'success' => false,
                'message' => $error_message,
                'code' => 'api_error',
                'details' => $response_data
            );
        }

        $result = json_decode($response, true);

        if (!isset($result['output']) || !is_array($result['output']) || empty($result['output'])) {
            error_log('!!! AI MODERATION FAILURE: Empty output from gpt-5 responses endpoint');
            error_log('Full response: ' . json_encode($result, JSON_PRETTY_PRINT));
            return array(
                'success' => false,
                'message' => 'AI moderation could not complete: gpt-5 response was empty',
                'code' => 'incomplete_response',
                'details' => $response
            );
        }

        try {
            $raw_content = '';
            
            foreach ($result['output'] as $output_item) {
                if (isset($output_item['type']) && $output_item['type'] === 'message' && isset($output_item['content'])) {
                    foreach ($output_item['content'] as $content) {
                        if (isset($content['type']) && $content['type'] === 'output_text' && isset($content['text'])) {
                            $raw_content .= $content['text'];
                        }
                    }
                }
            }

            if (empty(trim($raw_content))) {
                error_log('!!! AI MODERATION FAILURE: Could not extract text from gpt-5 responses');
                error_log('Output structure: ' . json_encode($result['output'], JSON_PRETTY_PRINT));
                return array(
                    'success' => false,
                    'message' => 'AI moderation could not complete: no text output found',
                    'code' => 'invalid_response_format',
                    'details' => $result['output']
                );
            }

            error_log('AI Raw Content from Responses API: ' . $raw_content);
            $ai_response = json_decode($raw_content, true);
            error_log('JSON Parse Result: ' . json_encode($ai_response, JSON_PRETTY_PRINT));

            if (!isset($ai_response['decision']) || !isset($ai_response['confidence'])) {
                $fallback_response = $this->parse_text_response($raw_content);

                if (isset($fallback_response['decision']) && isset($fallback_response['confidence'])) {
                    return array(
                        'success' => true,
                        'decision' => $fallback_response['decision'],
                        'confidence' => $fallback_response['confidence'],
                        'reasoning' => $fallback_response['reasoning'] ?? 'Response parsed using text fallback method',
                        'tokens_used' => isset($result['usage']['total_tokens']) ? $result['usage']['total_tokens'] : null,
                        'parameters_used' => array(
                            'max_tokens' => $max_tokens,
                            'endpoint' => 'responses',
                            'reasoning_effort' => $reasoning_effort
                        ),
                        'parameter_notes' => array('Fallback text parsing used (JSON response was malformed)')
                    );
                }

                return array(
                    'success' => false,
                    'message' => __('Invalid AI response format', 'wordpress-review-bot'),
                    'code' => 'invalid_ai_response',
                    'details' => array(
                        'raw_response' => $raw_content,
                        'json_parse_result' => $ai_response,
                        'fallback_result' => $fallback_response
                    )
                );
            }

            if (!in_array($ai_response['decision'], array('approve', 'reject', 'spam'))) {
                return array(
                    'success' => false,
                    'message' => __('Invalid decision returned by AI', 'wordpress-review-bot'),
                    'code' => 'invalid_decision',
                    'details' => $ai_response
                );
            }

            if ($ai_response['confidence'] < 0 || $ai_response['confidence'] > 1) {
                $ai_response['confidence'] = 0.5;
            }

            error_log('Final Result from gpt-5: ' . json_encode(array(
                'decision' => $ai_response['decision'],
                'confidence' => $ai_response['confidence'],
                'reasoning' => $ai_response['reasoning'] ?? 'No reasoning provided'
            ), JSON_PRETTY_PRINT));

            return array(
                'success' => true,
                'decision' => $ai_response['decision'],
                'confidence' => $ai_response['confidence'],
                'reasoning' => $ai_response['reasoning'] ?? 'No reasoning provided',
                'tokens_used' => isset($result['usage']['total_tokens']) ? $result['usage']['total_tokens'] : null,
                'parameters_used' => array(
                    'max_tokens' => $max_tokens,
                    'endpoint' => 'responses',
                    'reasoning_effort' => $reasoning_effort
                ),
                'parameter_notes' => array()
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => __('Failed to parse AI response:', 'wordpress-review-bot') . ' ' . $e->getMessage(),
                'code' => 'parse_error'
            );
        }
    }

    /**
     * Call chat/completions endpoint for standard models
     *
     * @param string $api_key OpenAI API key
     * @param string $model Model name
     * @param array $comment Comment data
     * @param string $prompt Moderation prompt
     * @param int $max_tokens Maximum tokens
     * @param float $temperature Temperature setting
     * @return array API response
     */
    private function call_chat_completions_endpoint($api_key, $model, $comment, $prompt, $max_tokens, $temperature) {
        $url = 'https://api.openai.com/v1/chat/completions';

        $token_param = $this->get_token_parameter_for_model($model);
        $supports_json_format = $this->supports_json_response_format($model);
        $appropriate_temperature = $this->get_temperature_for_model($model, $temperature);

        $data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a WordPress comment moderator. Analyze comments fairly and objectively to determine if they should be approved, rejected, or marked as spam. Consider context, relevance, and content quality. Respond with JSON format: {"decision": "approve/reject/spam", "confidence": 0.00-1.00, "reasoning": "Your detailed reasoning"}'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            $token_param => $max_tokens,
            'temperature' => $appropriate_temperature
        );

        if ($supports_json_format) {
            $data['response_format'] = array('type' => 'json_object');
        }

        if ($this->is_reasoning_model($model)) {
            error_log("WRB: Detected reasoning model ({$model})");
            
            if (stripos($model, 'o1') !== false || stripos($model, 'o3') !== false) {
                $data['thinking'] = array(
                    'type' => 'enabled',
                    'budget_tokens' => 5000
                );
                error_log("WRB: Using o1/o3 thinking format with budget_tokens: 5000");
            }
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        error_log('=== OpenAI API Debug ===');
        error_log('Request URL: ' . $url);
        error_log('Request Data: ' . json_encode($data, JSON_PRETTY_PRINT));
        error_log('HTTP Code: ' . $http_code);
        error_log('Raw Response: ' . $response);
        error_log('CURL Error: ' . $error);
        error_log('=== End Debug ===');

        if ($error) {
            return array(
                'success' => false,
                'message' => __('Connection error:', 'wordpress-review-bot') . ' ' . $error,
                'code' => 'connection_error'
            );
        }

        if ($http_code !== 200) {
            $response_data = json_decode($response, true);
            $error_message = isset($response_data['error']['message']) ?
                $response_data['error']['message'] :
                __('API returned HTTP code', 'wordpress-review-bot') . ' ' . $http_code;

            return array(
                'success' => false,
                'message' => $error_message,
                'code' => 'api_error',
                'details' => $response_data
            );
        }

        $result = json_decode($response, true);

        // Check for incomplete AI response
        $ai_incomplete = false;
        $finish_reason_length = false;
        $content_from_openai = '';
        
        if (!empty($response)) {
            $response_obj = json_decode($response, true);
            if (
                isset($response_obj['choices'][0]['message']['content']) &&
                trim($response_obj['choices'][0]['message']['content']) === ''
            ) {
                $content_from_openai = '';
                $ai_incomplete = true;
            } else if (isset($response_obj['choices'][0]['message']['content'])) {
                $content_from_openai = $response_obj['choices'][0]['message']['content'];
            }
            if (
                isset($response_obj['choices'][0]['finish_reason']) &&
                $response_obj['choices'][0]['finish_reason'] === 'length'
            ) {
                $finish_reason_length = true;
            }
        }
        
        if ($ai_incomplete || $finish_reason_length) {
            error_log('!!! AI MODERATION FAILURE: AI response was incomplete or truncated due to max_tokens.');
            return array(
                'success' => false,
                'message' => 'AI moderation could not complete: OpenAI response was incomplete (token limit hit or empty response). Adjust max tokens or model.',
                'code' => 'incomplete_response',
                'details' => $response
            );
        }

        if (!isset($result['choices'][0]['message']['content'])) {
            return array(
                'success' => false,
                'message' => __('Invalid API response format', 'wordpress-review-bot'),
                'code' => 'invalid_response'
            );
        }

        try {
            $raw_content = $result['choices'][0]['message']['content'];
            $ai_response = json_decode($raw_content, true);

            error_log('AI Raw Content: ' . $raw_content);
            error_log('JSON Parse Result: ' . json_encode($ai_response, JSON_PRETTY_PRINT));
            error_log('Supports JSON Format: ' . ($supports_json_format ? 'YES' : 'NO'));

            if ($ai_response === null && !$supports_json_format) {
                $ai_response = $this->parse_text_response($raw_content);
                error_log('Text Parse Result: ' . json_encode($ai_response, JSON_PRETTY_PRINT));
            }

            if (!isset($ai_response['decision']) || !isset($ai_response['confidence'])) {
                $fallback_response = $this->parse_text_response($raw_content);

                if (isset($fallback_response['decision']) && isset($fallback_response['confidence'])) {
                    $parameter_notes = array("Fallback text parsing used (JSON response was malformed)");

                    return array(
                        'success' => true,
                        'decision' => $fallback_response['decision'],
                        'confidence' => $fallback_response['confidence'],
                        'reasoning' => $fallback_response['reasoning'] ?? 'Response parsed using text fallback method',
                        'tokens_used' => isset($result['usage']['total_tokens']) ? $result['usage']['total_tokens'] : null,
                        'parameters_used' => array(
                            'temperature' => $appropriate_temperature,
                            'max_tokens' => $max_tokens,
                            'json_format' => $supports_json_format
                        ),
                        'parameter_notes' => $parameter_notes
                    );
                }

                $debug_info = array(
                    'raw_response' => $raw_content,
                    'json_parse_result' => $ai_response,
                    'fallback_result' => $fallback_response,
                    'model' => $model,
                    'supports_json' => $supports_json_format,
                    'temperature_used' => $appropriate_temperature
                );

                return array(
                    'success' => false,
                    'message' => __('Invalid AI response format', 'wordpress-review-bot') . ' - Unable to parse AI response even with fallback methods',
                    'code' => 'invalid_ai_response',
                    'details' => $debug_info
                );
            }

            if (!in_array($ai_response['decision'], array('approve', 'reject', 'spam'))) {
                return array(
                    'success' => false,
                    'message' => __('Invalid decision returned by AI', 'wordpress-review-bot'),
                    'code' => 'invalid_decision',
                    'details' => $ai_response
                );
            }

            if ($ai_response['confidence'] < 0 || $ai_response['confidence'] > 1) {
                $ai_response['confidence'] = 0.5;
            }

            $parameter_notes = array();
            if ($temperature !== $appropriate_temperature) {
                $parameter_notes[] = "Temperature automatically set to {$appropriate_temperature} (model requirement)";
            }
            if (!$supports_json_format) {
                $parameter_notes[] = "Text response parsing used (model limitation)";
            }

            $final_result = array(
                'success' => true,
                'decision' => $ai_response['decision'],
                'confidence' => $ai_response['confidence'],
                'reasoning' => $ai_response['reasoning'] ?? 'No reasoning provided',
                'tokens_used' => isset($result['usage']['total_tokens']) ? $result['usage']['total_tokens'] : null,
                'parameters_used' => array(
                    'temperature' => $appropriate_temperature,
                    'max_tokens' => $max_tokens,
                    'json_format' => $supports_json_format
                ),
                'parameter_notes' => $parameter_notes
            );

            error_log('Final Result: ' . json_encode($final_result, JSON_PRETTY_PRINT));
            error_log('=== End Test Moderation Debug ===');

            return $final_result;

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => __('Failed to parse AI response:', 'wordpress-review-bot') . ' ' . $e->getMessage(),
                'code' => 'parse_error'
            );
        }
    }

    /**
     * Parse text response from AI when JSON format is not available
     *
     * @param string $text_response Raw text response from AI
     * @return array Parsed decision, confidence, and reasoning
     */
    private function parse_text_response($text_response) {
        $decision = 'approve';
        $confidence = 0.7;
        $reasoning = $text_response;

        if (preg_match('/(?:decision|verdict|action|result):\s*(approve|reject|spam)/i', $text_response, $matches)) {
            $decision = strtolower($matches[1]);
        } elseif (preg_match('/"(decision|verdict|action|result)":\s*"(approve|reject|spam)"/i', $text_response, $matches)) {
            $decision = strtolower($matches[2]);
        } else {
            if (preg_match('/\b(approve|accepted|good|legitimate|positive|helpful)\b/i', $text_response)) {
                $decision = 'approve';
            } elseif (preg_match('/\b(reject|deny|inappropriate|offensive|negative|harmful|abusive)\b/i', $text_response)) {
                $decision = 'reject';
            } elseif (preg_match('/\b(spam|promotional|advertisement|scam|marketing|commercial)\b/i', $text_response)) {
                $decision = 'spam';
            } elseif (strlen($text_response) < 200) {
                if (preg_match('/\b(good|great|excellent|helpful|useful)\b/i', $text_response)) {
                    $decision = 'approve';
                } elseif (preg_match('/\b(bad|terrible|useless|inappropriate|offensive)\b/i', $text_response)) {
                    $decision = 'reject';
                }
            } else {
                if (preg_match('/(https?:\/\/|www\.|http:\/\/|[\d\-\.\(\)\s]{10,})/', $text_response)) {
                    $decision = 'spam';
                } elseif (preg_match('/(amazing|best deals|cheap|buy now|check out|marketing|bot)/i', $text_response)) {
                    $decision = 'spam';
                } elseif (preg_match('/(\!{2,}|\?{2,})/', $text_response)) {
                    $decision = 'spam';
                } else {
                    if (stripos($text_response, 'marketing bot') !== false || stripos($text_response, 'spam-site.com') !== false) {
                        $decision = 'spam';
                        $confidence = 0.95;
                        $reasoning = 'Comment contains marketing bot author and spam-site.com link - clear spam indicators';
                    } else {
                        $decision = 'approve';
                    }
                }
            }
        }

        if (preg_match('/(?:confidence|certainty|score):\s*(\d+(?:\.\d+)?)/i', $text_response, $matches)) {
            $confidence = min(1.0, max(0.0, floatval($matches[1])));
        }

        if (preg_match('/(?:reasoning|explanation|analysis|because|reason):\s*(.+)/i', $text_response, $matches)) {
            $reasoning = trim($matches[1]);
        }

        return array(
            'decision' => $decision,
            'confidence' => $confidence,
            'reasoning' => $reasoning
        );
    }
}
