<?php
for($i = 1; $i<=sizeof($cfg['Servers']); $i++) {
    $cfg['Servers'][$i]['auth_type'] = 'signon';
    $cfg['Servers'][$i]['SignonSession'] = 'SignonSession';
    $cfg['Servers'][$i]['SignonURL'] = '/oidc-signon.php?database=' . $cfg['Servers'][$i]['host'];
}