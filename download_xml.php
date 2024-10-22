<?php
if (isset($_POST['xml'])) {
  header('Content-Disposition: attachment; filename="pedidos.xml"');
  header('Content-Type: application/xml');
  echo $_POST['xml'];
  exit;
} else {
  echo 'There\'s no data to download';
}
?>