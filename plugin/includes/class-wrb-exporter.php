<?php
/**
 * Exporter Module
 * Handles exporting AI decisions in various formats
 */

if (!defined('ABSPATH')) {
    exit;
}

class WRB_Exporter {

    /**
     * Export decisions as CSV
     *
     * @param array $decisions Array of decision objects
     */
    public function export_csv($decisions) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="ai-decisions-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, array(
            'ID',
            'Comment ID',
            'Author',
            'Decision',
            'Confidence',
            'Reasoning',
            'Model Used',
            'Processing Time',
            'Date',
            'Overridden',
            'Overridden By',
            'Overridden At'
        ));

        foreach ($decisions as $decision) {
            fputcsv($output, array(
                $decision->id,
                $decision->comment_id,
                isset($decision->comment_author) ? $decision->comment_author : '',
                $decision->decision,
                $decision->confidence,
                strip_tags($decision->reasoning),
                $decision->model_used,
                $decision->processing_time,
                $decision->created_at,
                $decision->overridden ? 'Yes' : 'No',
                isset($decision->overridden_by) ? $decision->overridden_by : '',
                isset($decision->overridden_at) ? $decision->overridden_at : ''
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * Export decisions as JSON
     *
     * @param array $decisions Array of decision objects
     */
    public function export_json($decisions) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="ai-decisions-' . date('Y-m-d') . '.json"');

        $export_data = array();
        foreach ($decisions as $decision) {
            $export_data[] = array(
                'id' => $decision->id,
                'comment_id' => $decision->comment_id,
                'comment_author' => isset($decision->comment_author) ? $decision->comment_author : null,
                'comment_content' => isset($decision->comment_content) ? $decision->comment_content : null,
                'decision' => $decision->decision,
                'confidence' => floatval($decision->confidence),
                'reasoning' => $decision->reasoning,
                'model_used' => $decision->model_used,
                'processing_time' => floatval($decision->processing_time),
                'created_at' => $decision->created_at,
                'post_title' => isset($decision->post_title) ? $decision->post_title : null,
                'overridden' => (bool) $decision->overridden,
                'overridden_by' => isset($decision->overridden_by) ? $decision->overridden_by : null,
                'overridden_at' => isset($decision->overridden_at) ? $decision->overridden_at : null
            );
        }

        echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Export decisions as XML
     *
     * @param array $decisions Array of decision objects
     */
    public function export_xml($decisions) {
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="ai-decisions-' . date('Y-m-d') . '.xml"');

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><decisions></decisions>');

        foreach ($decisions as $decision) {
            $decision_node = $xml->addChild('decision');
            $decision_node->addChild('id', $decision->id);
            $decision_node->addChild('comment_id', $decision->comment_id);
            $decision_node->addChild('comment_author', isset($decision->comment_author) ? htmlspecialchars($decision->comment_author) : '');
            $decision_node->addChild('decision_type', $decision->decision);
            $decision_node->addChild('confidence', $decision->confidence);
            $decision_node->addChild('reasoning', htmlspecialchars($decision->reasoning));
            $decision_node->addChild('model_used', $decision->model_used);
            $decision_node->addChild('processing_time', $decision->processing_time);
            $decision_node->addChild('created_at', $decision->created_at);
            $decision_node->addChild('overridden', $decision->overridden ? 'true' : 'false');
            if (isset($decision->overridden_by)) {
                $decision_node->addChild('overridden_by', htmlspecialchars($decision->overridden_by));
            }
            if (isset($decision->overridden_at)) {
                $decision_node->addChild('overridden_at', $decision->overridden_at);
            }
        }

        echo $xml->asXML();
        exit;
    }
}
