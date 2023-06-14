<?php
for($i = 1; $i<=sizeof($cfg['Servers']); $i++) {
    $cfg['Servers'][$i]['auth_type'] = 'signon';
    $cfg['Servers'][$i]['SignonSession'] = 'SignonSession';
    $cfg['Servers'][$i]['SignonURL'] = '/oidc-signon.php?database=' . urlencode(base64_encode($cfg['Servers'][$i]['user'] . '@' . $cfg['Servers'][$i]['host']));
}