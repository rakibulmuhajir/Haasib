<?php
$input = json_decode(stream_get_contents(STDIN), true);
$cfg = $input['config'];
if (!preg_match('/_(test|testing)$/', $cfg['database'])) { exit(9); }
$pdo = new PDO("pgsql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']}", $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();
$pdo->exec("SET LOCAL statement_timeout='8s'");
$lock = $pdo->prepare('SELECT pg_advisory_xact_lock(hashtext(?), hashtext(?))');
$lock->execute([$input['company'], '2026-09-15']);
echo "LOCKED\n"; flush();
$row = $pdo->prepare('SELECT id FROM acct.bill_payments WHERE id=? FOR UPDATE');
$row->execute([$input['payment']]);
$pdo->commit();
echo "COMMITTED\n"; flush();
