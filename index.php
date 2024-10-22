<?php

// Activate or deactivate logging with the debug variable
$debug = false;  // Set to false if you don't want to log

if (!isset($_GET['pwd']) || !isset($_GET['dias']) || !isset($_GET['estado'])) {
  echo 'Missing parameter(s)';
  exit;
}

$password = "YOUR_PASSWORD_HERE";
if ($_GET['pwd'] !== $password) {
  echo 'Incorrect password';
  exit;
}

$shop = 'YOUR_SHOP_ID_HERE';
$access_token = 'YOUR_ACCESS_TOKEN_HERE';
$days = $_GET['dias'];
$status = $_GET['estado']; 

// Convert the status to valid Shopify values
switch ($status) {
  case '1':
    $status = 'authorized';
    break;
  case '2':
    $status = 'pending';
    break;
  case '3':
    $status = 'paid';
    break;
  case '4':
    $status = 'partially_paid';
    break;
  case '5':
    $status = 'refunded';
    break;
  case '6':
    $status = 'voided';
    break;
  case '7':
    $status = 'partially_refunded';
    break;
  case '8':
    $status = 'any';
    break;
  case '9':
    $status = 'unpaid';
    break;
  default:
    $status = 'completed';
}

// Call the function
getProducts($shop, $access_token, $status, $days, $debug);

function getProducts($shop, $access_token, $status = 'completed', $days = 30, $debug = false)
{
  // Calculate the lower and upper date limits in ISO 8601 format
  $fecha_limite_inferior = date('Y-m-d\TH:i:sO', strtotime("-$days days")); // $days days ago
  $fecha_actual = date('Y-m-d\TH:i:sO'); // Current date

  // URL of the Shopify REST API with the correct parameters
  $url = "https://$shop.myshopify.com/admin/api/2024-10/orders.json?status=any&financial_status=$status&created_at_min=$fecha_limite_inferior&created_at_max=$fecha_actual";

  // Set the request header
  $headers = [
    "Content-Type: application/json",
    "X-Shopify-Access-Token: $access_token"
  ];

  // Initialize cURL
  $ch = curl_init($url);

  // Set cURL options
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // To get the response as a string
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Add headers
  curl_setopt($ch, CURLOPT_HTTPGET, true); // Indicate that it's a GET request

  // Execute the request
  $response = curl_exec($ch);

  // Check if there was an error
  if (curl_errno($ch)) {
    echo 'Error in cURL: ' . curl_error($ch);
  } else {
    // Convert the JSON response to an array
    $data = json_decode($response, true);

    if (isset($data['errors'])) {
      // Handle errors if there are any in the response
      echo 'Error in the Shopify response: ' . json_encode($data['errors']);
    } else {
      // Create a new XML object
      $xml = new SimpleXMLElement('<pedidos/>');

      // Show a summary of the orders
      echo '<h2>Summary of Found Orders</h2>';
      echo '<table border="1">';
      echo '<tr><th>Order Date</th><th>Reference</th><th>Customer</th><th>E-Mail</th></tr>';

      // Iterate over the orders and add them to the XML and the summary HTML
      foreach ($data['orders'] as $order) {
        $orderNode = $xml->addChild('pedido');

        // Order date
        $createdAt = date('j-n-Y', strtotime($order['created_at']));
        $orderNode->addChild('FechaPedido', $createdAt);

        // Order number or reference
        $orderNode->addChild('NumPedido', $order['name']);

        // Customer ID and name
        $idCliente = isset($order['customer']) ? str_pad($order['customer']['id'], 8, '0', STR_PAD_LEFT) : 'N/A';
        $nombreCliente = isset($order['customer']) ? $order['customer']['first_name'] . ' ' . $order['customer']['last_name'] : 'Unknown Customer';
        $orderNode->addChild('IdCliente', $idCliente);
        $orderNode->addChild('Nombre', $nombreCliente);

        // Customer email
        $emailCliente = isset($order['customer']) ? $order['customer']['email'] : 'Not available';
        $orderNode->addChild('E-Mail', $emailCliente);

        // Add the information to the summary on screen
        echo "<tr>
                <td>$createdAt</td>
                <td>{$order['name']}</td>
                <td>$nombreCliente</td>
                <td>$emailCliente</td>
              </tr>";
      }

      echo '</table>';

      // Button to download the XML file
      echo '<form method="post" action="download_xml.php">';
      echo '<input type="hidden" name="xml" value="' . htmlspecialchars($xml->asXML()) . '">';
      echo '<button type="submit">Download XML</button>';
      echo '</form>';

      // If debug is enabled, log the API response
      if ($debug) {
        // Check if the log directory exists, if not create it
        if (!file_exists('log')) {
          mkdir('log', 0777, true);
        }

        // Save the response to a log file
        $logFile = fopen('log/response-' . date('Y-m-d_H-i-s') . '.json', 'w');
        fwrite($logFile, json_encode(json_decode($response), JSON_PRETTY_PRINT));
        fclose($logFile);
      }
    }
  }

  curl_close($ch);
}
