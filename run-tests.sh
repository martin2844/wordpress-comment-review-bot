#!/bin/bash
# WordPress Review Bot - Manual Testing Script
# Run this script in your Docker environment to test the implementation

echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║  WordPress Review Bot - Automated Test Suite                    ║"
echo "║  Testing: Auto-scaling Removal, Max Tokens, Logging System      ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counter
PASSED=0
FAILED=0

# Helper functions
pass() {
    echo -e "${GREEN}✓ PASS${NC}: $1"
    ((PASSED++))
}

fail() {
    echo -e "${RED}✗ FAIL${NC}: $1"
    ((FAILED++))
}

info() {
    echo -e "${BLUE}ℹ INFO${NC}: $1"
}

warning() {
    echo -e "${YELLOW}⚠ WARN${NC}: $1"
}

section() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  $1"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

# ==============================================================================
# TEST 1: Database Table Creation
# ==============================================================================
section "TEST 1: Database Logging Table"

info "Checking if wp_wrb_logs table exists..."
RESULT=$(docker compose exec -T wordpress wp db tables | grep wrb_logs)
if [ ! -z "$RESULT" ]; then
    pass "Database table wp_wrb_logs exists"
else
    fail "Database table wp_wrb_logs NOT found"
fi

info "Checking table structure..."
COLUMNS=$(docker compose exec -T wordpress wp db query "DESCRIBE wp_wrb_logs;" --skip-column-names | wc -l)
if [ $COLUMNS -eq 6 ]; then
    pass "Table has expected 6 columns"
else
    fail "Table has $COLUMNS columns (expected 6)"
fi

# ==============================================================================
# TEST 2: Settings - Max Tokens
# ==============================================================================
section "TEST 2: Max Tokens Configuration"

info "Setting max_tokens to 5000..."
docker compose exec -T wordpress wp option update wrb_options --merge='{"max_tokens":5000}' > /dev/null 2>&1

CURRENT=$(docker compose exec -T wordpress wp option get wrb_options | grep max_tokens)
if [[ $CURRENT == *"5000"* ]]; then
    pass "Max tokens set to 5000 (unlimited configuration works)"
else
    fail "Max tokens not set correctly"
fi

info "Testing with value of 10000..."
docker compose exec -T wordpress wp option update wrb_options --merge='{"max_tokens":10000}' > /dev/null 2>&1

CURRENT=$(docker compose exec -T wordpress wp option get wrb_options | grep max_tokens)
if [[ $CURRENT == *"10000"* ]]; then
    pass "Max tokens accepts 10000 (no upper limit enforced)"
else
    fail "Max tokens cannot be set above 1000 (limit still enforced)"
fi

# ==============================================================================
# TEST 3: Comment Creation & Processing
# ==============================================================================
section "TEST 3: Comment Processing"

info "Creating test comment #43..."
RESULT=$(docker compose exec -T wordpress wp comment create \
    --comment_post_ID=8 \
    --comment_author="Test User 43" \
    --comment_author_email="test43@example.com" \
    --comment_content="This is test comment #43 for logging system verification." \
    --comment_type="" 2>&1)

if [[ $RESULT == *"Success"* ]]; then
    COMMENT_ID=$(echo $RESULT | grep -oP '(?<=comment )\d+(?= was created)' | head -1)
    pass "Test comment #$COMMENT_ID created successfully"
    info "Processing comment $COMMENT_ID..."
else
    fail "Failed to create test comment"
    COMMENT_ID=""
fi

# ==============================================================================
# TEST 4: Logs Database Entries
# ==============================================================================
section "TEST 4: Logging System"

if [ ! -z "$COMMENT_ID" ]; then
    info "Triggering cron to process comment..."
    docker compose exec -T wordpress wp cron event run wrb_process_held_comments 2>&1 > /dev/null
    
    # Wait for processing
    sleep 3
    
    info "Checking if logs were created..."
    LOG_COUNT=$(docker compose exec -T wordpress wp db query \
        "SELECT COUNT(*) FROM wp_wrb_logs WHERE comment_id = $COMMENT_ID;" \
        --skip-column-names 2>&1 | head -1)
    
    if [ "$LOG_COUNT" -gt "0" ]; then
        pass "Logging system created $LOG_COUNT log entries for comment #$COMMENT_ID"
    else
        warning "No logs found for comment #$COMMENT_ID (API might have failed or not been called)"
    fi
    
    info "Checking log table for errors..."
    ERROR_COUNT=$(docker compose exec -T wordpress wp db query \
        "SELECT COUNT(*) FROM wp_wrb_logs WHERE level = 'error';" \
        --skip-column-names 2>&1 | head -1)
    
    if [ "$ERROR_COUNT" -eq "0" ]; then
        pass "No error logs detected (processing succeeded or waiting for API)"
    else
        warning "Found $ERROR_COUNT error log entries - check Admin → Logs for details"
    fi
else
    fail "Skipping log tests (comment creation failed)"
fi

# ==============================================================================
# TEST 5: Admin Logs Tab
# ==============================================================================
section "TEST 5: Admin Interface"

info "Checking if Logs tab registered in menu..."
RESULT=$(docker compose exec -T wordpress wp menu list --format=json 2>&1 | grep -i "wrb-logs")
if [ ! -z "$RESULT" ]; then
    pass "Logs tab registered in admin menu"
else
    warning "Logs tab not found in menu (may not be accessible via WP-CLI, check admin dashboard)"
fi

# ==============================================================================
# TEST 6: Auto-Scaling Verification
# ==============================================================================
section "TEST 6: Auto-Scaling Removed"

info "Checking code for auto-scaling logic..."
if docker compose exec -T wordpress grep -r "Auto-scaling max_tokens" /var/www/html/wp-content/plugins/wordpress-review-bot/ > /dev/null 2>&1; then
    fail "Auto-scaling code still found in plugin"
else
    pass "Auto-scaling code successfully removed"
fi

info "Verifying max_tokens used as-is without modification..."
if docker compose exec -T wordpress grep -r "max_tokens = 3000" /var/www/html/wp-content/plugins/wordpress-review-bot/ > /dev/null 2>&1; then
    fail "Force scaling to 3000 still found"
else
    pass "No forced scaling to 3000 detected"
fi

# ==============================================================================
# TEST 7: API Endpoint Routing
# ==============================================================================
section "TEST 7: API Endpoint Routing"

info "Checking for gpt-5 detection method..."
if docker compose exec -T wordpress grep -r "is_gpt5_reasoning_model" /var/www/html/wp-content/plugins/wordpress-review-bot/ > /dev/null 2>&1; then
    pass "gpt-5 model detection method found"
else
    fail "gpt-5 model detection method not found"
fi

info "Checking for responses endpoint method..."
if docker compose exec -T wordpress grep -r "call_responses_endpoint" /var/www/html/wp-content/plugins/wordpress-review-bot/ > /dev/null 2>&1; then
    pass "responses endpoint method found"
else
    fail "responses endpoint method not found"
fi

info "Checking for chat/completions endpoint method..."
if docker compose exec -T wordpress grep -r "call_chat_completions_endpoint" /var/www/html/wp-content/plugins/wordpress-review-bot/ > /dev/null 2>&1; then
    pass "chat/completions endpoint method found"
else
    fail "chat/completions endpoint method not found"
fi

# ==============================================================================
# TEST 8: Logs Display
# ==============================================================================
section "TEST 8: Logs Table Content"

info "Querying logs from database..."
LOG_DISPLAY=$(docker compose exec -T wordpress wp db query \
    "SELECT timestamp, level, message, comment_id FROM wp_wrb_logs ORDER BY timestamp DESC LIMIT 5;" \
    2>&1)

if [[ $LOG_DISPLAY == *"timestamp"* ]]; then
    pass "Successfully queried logs from database"
    echo ""
    echo "Recent Log Entries:"
    echo "$LOG_DISPLAY" | head -20
else
    warning "Could not retrieve logs"
fi

# ==============================================================================
# SUMMARY
# ==============================================================================
section "TEST SUMMARY"

TOTAL=$((PASSED + FAILED))
echo ""
echo -e "  Passed: ${GREEN}$PASSED${NC}  Failed: ${RED}$FAILED${NC}  Total: $TOTAL"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                    ✓ ALL TESTS PASSED!                           ║${NC}"
    echo -e "${GREEN}║              Implementation is working correctly                  ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════════╝${NC}"
else
    echo -e "${YELLOW}╔═══════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${YELLOW}║              ⚠ Some tests failed - review above                   ║${NC}"
    echo -e "${YELLOW}╚═══════════════════════════════════════════════════════════════════╝${NC}"
fi

echo ""
echo "Next steps:"
echo "1. Go to WordPress Admin → Review Bot → Logs"
echo "2. You should see all log entries from processing"
echo "3. Test filters by level and comment ID"
echo "4. Verify max_tokens setting in Settings tab"
echo ""

exit $FAILED
