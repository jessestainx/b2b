<?php
use Magento\Framework\App\Bootstrap;
require '/home/jessessh/htdocs/srv1113343.hstgr.cloud/app/bootstrap.php';
$bootstrap = Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$state = $obj->get(\Magento\Framework\App\State::class);
$state->setAreaCode('adminhtml');
$conn  = $obj->get(\GrupoAwamotos\ERPIntegration\Model\Connection::class);
$res   = $obj->get(\Magento\Framework\App\ResourceConnection::class);
$mysql = $res->getConnection();
$helper = $obj->get(\GrupoAwamotos\ERPIntegration\Helper\Data::class);

$dsn = sprintf('dblib:host=%s:%d;dbname=%s;version=7.4;charset=UTF-8',
    $helper->getHost(), $helper->getPort(), $helper->getDatabase());
$writ = new PDO($dsn, $helper->getWriteUsername(), $helper->getWritePassword(),
    [PDO::ATTR_TIMEOUT=>60, PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

// Buscar SOMENTE os que ainda estão zerados
$rows = $conn->query(
    "SELECT f.CODIGO, v.CHAVEEXTERNA
     FROM FN_FORNECEDORES f
     INNER JOIN GR_INTEGRACAOVALIDADOR v ON v.CHAVE=CAST(f.CODIGO AS VARCHAR)
         AND v.INTEGRACAOORIGEM='753ADB36-27F8-4910-84BB-D7E26279C5A8'
     WHERE REPLACE(REPLACE(REPLACE(ISNULL(f.CGC,''),'.',''),'/',''),'-','')='00000000000000'
       AND f.CODIGO BETWEEN 19233 AND 26000
     ORDER BY f.CODIGO"
);
echo count($rows) . " CNPJs zerados para corrigir\n";

$stmt = $writ->prepare(
    "UPDATE FN_FORNECEDORES SET CGC=? WHERE CODIGO=? AND REPLACE(REPLACE(REPLACE(ISNULL(CGC,''),'.',''),'/',''),'-','')='00000000000000'"
);
$updated=0; $skip=0;
foreach ($rows as $i => $r) {
    $raw = $mysql->fetchOne(
        "SELECT JSON_UNQUOTE(JSON_EXTRACT(custom_field, '$.\"6\"')) FROM oc_pre_registration WHERE customer_id=?",
        [(int)$r['CHAVEEXTERNA']]
    );
    if (!$raw || strlen(preg_replace('/\D/','',$raw)) < 11) { $skip++; continue; }
    $d = preg_replace('/\D/','',$raw);
    $fmt = strlen($d)==14
        ? substr($d,0,2).'.'.substr($d,2,3).'.'.substr($d,5,3).'/'.substr($d,8,4).'-'.substr($d,12,2)
        : substr($d,0,3).'.'.substr($d,3,3).'.'.substr($d,6,3).'-'.substr($d,9,2);
    $stmt->execute([$fmt, (int)$r['CODIGO']]);
    $updated++;
    if ($updated % 500 == 0) echo "$updated atualizados...\n";
}
echo "CONCLUIDO: updated=$updated skip=$skip\n";
// verificacao final
$zero = $conn->query("SELECT COUNT(*) AS n FROM FN_FORNECEDORES f INNER JOIN GR_INTEGRACAOVALIDADOR v ON v.CHAVE=CAST(f.CODIGO AS VARCHAR) AND v.INTEGRACAOORIGEM='753ADB36-27F8-4910-84BB-D7E26279C5A8' WHERE REPLACE(REPLACE(REPLACE(ISNULL(f.CGC,''),'.',''),'/',''),'-','')='00000000000000' AND f.CODIGO BETWEEN 19233 AND 26000");
echo "Zerados restantes: " . ($zero[0]['n'] ?? '?') . "\n";
