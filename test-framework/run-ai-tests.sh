#!/bin/bash
# Test runner for StoryOS AI Editor module tests

set -e

echo "=========================================="
echo "StoryOS AI Editor Test Suite"
echo "=========================================="

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test results tracking
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Function to print test results
print_result() {
    local test_name=$1
    local result=$2
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    if [ $result -eq 0 ]; then
        PASSED_TESTS=$((PASSED_TESTS + 1))
        echo -e "${GREEN}✓${NC} $test_name"
    else
        FAILED_TESTS=$((FAILED_TESTS + 1))
        echo -e "${RED}✗${NC} $test_name"
    fi
}

# Change to test-framework directory
cd "$(dirname "$0")/.."

echo ""
echo "Running PHPUnit tests..."
echo "=========================================="

# Run PHPUnit tests for AI Editor
phpunit_output=$(phpunit -c test-framework/phpunit.xml --filter "AI" 2>&1) || true
echo "$phpunit_output"

# Check if PHPUnit tests passed
if echo "$phpunit_output" | grep -q "OK.*tests"; then
    print_result "PHPUnit AI Editor tests" 0
else
    print_result "PHPUnit AI Editor tests" 1
fi

echo ""
echo "Running Python tests..."
echo "=========================================="

# Run pytest for AI Editor
pytest_output=$(pytest test-framework/tests/python/test_ai_editor.py -v 2>&1) || true
echo "$pytest_output"

# Check if pytest tests passed
if echo "$pytest_output" | grep -q "passed"; then
    print_result "Python AI Editor tests" 0
else
    print_result "Python AI Editor tests" 1
fi

echo ""
echo "=========================================="
echo "Test Summary"
echo "=========================================="
echo -e "Total: ${TOTAL_TESTS}"
echo -e "${GREEN}Passed: ${PASSED_TESTS}${NC}"
echo -e "${RED}Failed: ${FAILED_TESTS}${NC}"
echo ""

if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed.${NC}"
    exit 1
fi
