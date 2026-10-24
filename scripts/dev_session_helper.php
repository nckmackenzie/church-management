<?php
// DEV ONLY - never deployed (git-ignored: scripts/dev_*). Used by scripts/test_8_9_http.php.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }

$sessions = [];
$phpErrors = [];
/** A PHP session for an existing dev user, written the way Users::createUserSession() does. */
function mkSession($uid, $cong = null) {
    global $pdo, $sessions;
    $u = q('SELECT u.ID,u.UserName,u.UsertypeId,u.CongregationId,c.CongregationName,c.IsParish FROM tblusers u LEFT JOIN tblcongregation c ON c.ID=u.CongregationId WHERE u.ID=?', [$uid])[0];
    $sid = 'dev' . bin2hex(random_bytes(8));
    $data = ['userId' => $u['ID'], 'userName' => $u['UserName'], 'userType' => $u['UsertypeId'], 'congId' => $cong ?? $u['CongregationId'],
             'isParish' => $u['IsParish'], 'congName' => $u['CongregationName'], 'one' => 1, 'zero' => 0, 'processdate' => date('d-m-Y')];
    $enc = '';
    foreach ($data as $k => $v) { $enc .= $k . '|' . serialize($v); }
    file_put_contents(session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $sid, $enc);
    $sessions[] = $sid;
    return $sid;
}
