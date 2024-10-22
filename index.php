<?php

$debug = false;

if (!isset($_GET['pwd']) || !isset($_GET['dias'])) {
  echo 'Missing parameter(s)';
  exit;
}

$password = "YOUR-PASSWORD";
if ($_GET['pwd'] !== $password) {
  echo 'Incorrect password';
  exit;
}

$shop = 'YOUR-SHOP';
$access_token = 'YOUR-ACCESS-TOKEN';
$days = filter_input(INPUT_GET, 'dias', FILTER_VALIDATE_INT);

if ($days === false) {
  echo 'Invalid parameters';
  exit;
}

getOrdersByDeliveryDate($shop, $access_token, $days, $debug);

function getOrdersByDeliveryDate($shop, $access_token, $days = 0, $debug = false)
{
  $fecha_entrega_objetivo = date('Y-m-d', strtotime("-$days days"));

  // Construir la consulta GraphQL
  $query = '{
      orders(first: 50, query: "fulfillment_status: fulfilled delivered_at: ' . $fecha_entrega_objetivo . '") {
        edges {
          node {
            id
            name
            createdAt
            customer {
              id
              firstName
              lastName
              email
            }
          }
        }
      }
    }';

  // URL de la API de GraphQL de Shopify
  $url = "https://$shop.myshopify.com/admin/api/2024-10/graphql.json";

  $headers = [
    "Content-Type: application/json",
    "X-Shopify-Access-Token: $access_token"
  ];

  // Inicializar cURL
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $query]));

  $response = curl_exec($ch);

  if (curl_errno($ch)) {
    echo 'Error in cURL: ' . curl_error($ch);
    curl_close($ch);
    exit;
  }

  $data = json_decode($response, true);
  if (isset($data['errors'])) {
    echo 'Error in the Shopify response: ' . json_encode($data['errors']);
    curl_close($ch);
    exit;
  }

  $xml = new SimpleXMLElement('<pedidos/>');
  echo '<h2>Summary of Orders Delivered ' . $days . ' Days Ago ' . $fecha_entrega_objetivo . '</h2>';
  echo '<table border="1">';
  echo '<tr><th>Order Date</th><th>Reference</th><th>Customer</th><th>E-Mail</th><th>Delivery Date</th></tr>';

  foreach ($data['data']['orders']['edges'] as $edge) {
    $order = $edge['node'];
    if (isset($order['fulfillments']) && !empty($order['fulfillments'])) {
      foreach ($order['fulfillments'] as $fulfillment) {
        if (isset($fulfillment['deliveredAt'])) {
          $deliveredAt = date('Y-m-d', strtotime($fulfillment['deliveredAt']));
          if ($deliveredAt === $fecha_entrega_objetivo) {
            $createdAt = date('j-n-Y', strtotime($order['createdAt']));
            $idCliente = isset($order['customer']) ? str_pad($order['customer']['id'], 8, '0', STR_PAD_LEFT) : 'N/A';
            $nombreCliente = isset($order['customer']) ? htmlspecialchars($order['customer']['firstName'] . ' ' . $order['customer']['lastName'], ENT_XML1, 'UTF-8') : 'Unknown Customer';
            $emailCliente = isset($order['customer']) ? htmlspecialchars($order['customer']['email'], ENT_XML1, 'UTF-8') : 'Not available';

            $xml->addChild('pedido')->addChild('FechaPedido', $createdAt);
            $xml->addChild('NumPedido', htmlspecialchars($order['name'], ENT_XML1, 'UTF-8'));
            $xml->addChild('IdCliente', $idCliente);
            $xml->addChild('Nombre', $nombreCliente);
            $xml->addChild('E-Mail', $emailCliente);
            $xml->addChild('FechaEntrega', $deliveredAt);
            echo "<tr>
                  <td>$createdAt</td>
                  <td>{$order['name']}</td>
                  <td>$nombreCliente</td>
                  <td>$emailCliente</td>
                  <td>$deliveredAt</td>
                </tr>";
          }
        }
      }
    }
  }

  echo '</table>';

  echo '<form method="post" action="download_xml.php">';
  echo '<input type="hidden" name="xml" value="' . htmlspecialchars($xml->asXML()) . '">';
  echo '<button type="submit">Download XML</button>';
  echo '</form>';

  curl_close($ch);
}
