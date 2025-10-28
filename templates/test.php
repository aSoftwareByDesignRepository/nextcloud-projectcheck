<?php

/**
 * Test template for CSP debugging
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */
?>

<div id="app">
    <div id="app-content">
        <div id="app-content-wrapper">
            <h1><?php p($l->t('CSP Test Page')); ?></h1>
            <p><?php p($message); ?></p>

            <div id="test-results">
                <h2>Test Results:</h2>
                <div id="csp-test">CSP Test - This should work if CSP is configured correctly</div>
                <div id="csp-headers">
                    <h3>CSP Headers:</h3>
                    <p>Check browser developer tools Network tab to see CSP headers</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?php p($_['cspNonce']) ?>">
    // Test if JavaScript works
    console.log('CSP Test: JavaScript is working');
    try {
        document.getElementById('csp-test').innerHTML = 'CSP Test: JavaScript is working!';
        console.log('CSP Test: DOM manipulation successful');
    } catch (error) {
        console.error('CSP Test: Error:', error);
        document.getElementById('csp-test').innerHTML = 'CSP Test: Error - ' + error.message;
    }
</script>