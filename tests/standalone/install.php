<?php
$base = dirname(__DIR__, 2);
foreach (['EnvEditor','DatabaseProbe','Requirements'] as $c) require "$base/app/Cms/Install/$c.php";
use App\Cms\Install\{EnvEditor, DatabaseProbe, Requirements};
$ok=0;$bad=0; function t($l,$a,$e){global $ok,$bad; if($a===$e){$ok++;echo "  ✓ $l\n";}else{$bad++;echo "  ✗ $l\n     ".var_export($a,true)."\n";}}

echo "EnvEditor\n";
$f = sys_get_temp_dir().'/bwcms-test.env';
file_put_contents($f, "APP_NAME=Test\nAPP_KEY=base64:abc\nDB_CONNECTION=sqlite\n# DB_HOST=127.0.0.1\n# DB_PASSWORD=\nOTHER=keep # comment\n");
$e = new EnvEditor($f);
$e->set(['DB_CONNECTION'=>'mysql','DB_HOST'=>'localhost','DB_PASSWORD'=>'p@ss w#rd$"x','DB_PREFIX'=>'bw_','APP_DEBUG'=>false,'APP_URL'=>'https://site.com/sub']);
$c = file_get_contents($f);
t('replaces active key', str_contains($c, "DB_CONNECTION=mysql\n"), true);
t('uncomments commented key in place', str_contains($c, "DB_HOST=localhost\n") && !str_contains($c, '# DB_HOST'), true);
t('appends new key', str_contains($c, "DB_PREFIX=bw_"), true);
t('bool formatting', str_contains($c, "APP_DEBUG=false"), true);
t('keeps other lines + APP_KEY', str_contains($c, "OTHER=keep # comment") && str_contains($c, "APP_KEY=base64:abc"), true);
t('special chars round-trip', $e->get('DB_PASSWORD'), 'p@ss w#rd$"x');
$e->set(['DB_PASSWORD'=>"it's \$HOME"]);
t('single quote + $ round-trip', $e->get('DB_PASSWORD'), "it's \$HOME");
t('url unquoted', $e->get('APP_URL'), 'https://site.com/sub');
// Check phpdotenv-compatible quoting rules: single-quoted literal when possible
t('single-quote style', EnvEditor::format('a b$c'), "'a b\$c'");
t('double-quote escape', EnvEditor::format("a'b\"c\$"), '"a\'b\\"c\\$"');
try { $e->set(['bad-key'=>1]); t('rejects bad keys', false, true);} catch (RuntimeException $x) { t('rejects bad keys', true, true); }

echo "DatabaseProbe\n";
$p = new DatabaseProbe;
@unlink(sys_get_temp_dir().'/bwcms-site.sqlite');
$r = $p->test(['driver'=>'sqlite','database'=>sys_get_temp_dir().'/bwcms-site.sqlite','prefix'=>'bw_']);
t('sqlite ok + creates file', [$r['ok'], is_file(sys_get_temp_dir().'/bwcms-site.sqlite')], [true, true]);
t('sqlite version reported', (bool) preg_match('/^3\./', (string)$r['version']), true);
$pdo = new PDO('sqlite:'.sys_get_temp_dir().'/bwcms-site.sqlite'); $pdo->exec('create table bw_options (id int)'); $pdo->exec('create table bw_posts (id int)'); $pdo->exec('create table other (id int)');
$r = $p->test(['driver'=>'sqlite','database'=>sys_get_temp_dir().'/bwcms-site.sqlite','prefix'=>'bw_']);
t('detects existing install tables w/ prefix', $r['existing_tables'], 2);
t('different prefix = clean', $p->test(['driver'=>'sqlite','database'=>sys_get_temp_dir().'/bwcms-site.sqlite','prefix'=>'x_'])['existing_tables'], 0);
t('sqlite missing folder', $p->test(['driver'=>'sqlite','database'=>'/nope/x.sqlite'])['ok'], false);
$r = $p->test(['driver'=>'mysql','host'=>'127.0.0.1','port'=>3399,'database'=>'cms','username'=>'u','password'=>'p']);
t('mysql unreachable → friendly message', [$r['ok'], str_contains($r['message'], 'reach the database server')], [false, true]);
t('invalid db name rejected', $p->test(['driver'=>'mysql','host'=>'x','database'=>'bad;name','username'=>'u'])['ok'], false);
t('unknown driver', $p->test(['driver'=>'oracle'])['ok'], false);
t('missing extension', str_contains($p->test(['driver'=>'sqlsrv','database'=>'x'])['message'], 'pdo_sqlsrv'), true);

echo "Requirements\n";
$req = (new Requirements($base))->check();
t('groups', array_keys($req['groups']), ['PHP','PHP extensions','Database drivers','Writable folders']);
t('passes on this server', $req['passes'], true);
echo "\n$ok passed, $bad failed\n";
