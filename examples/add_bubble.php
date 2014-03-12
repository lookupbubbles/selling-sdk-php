<?php
require_once('../lib/Bubbles.php');

$username = '<<YOUR_ACCOUNT_EMAIL>>';
$password = '<<YOUR_API_KEY>>';

// Full category list here: https://www.lookupbubbles.com/Common/DownloadBubbleCategoryList or use $bubbles->getCategories()
$categoryID = 4259; // Toys & Games > Toys > Flying Toys > Kites

// Set the log file to null to disable logging
$logFile = null;

$bubbles = new Bubbles($username, $password, $logFile, BUBBLES_API_TEST_WSDL);

// Add a bubble

$title = 'Product Title';
$description = 'Product Description';
// Full category list here: https://www.lookupbubbles.com/Common/DownloadBubbleCategoryList
$categoryID = 4259; // Toys & Games > Toys > Flying Toys > Kites
$price = 123;
$oldPrice = 234;
$weight = null;
$conditionID = 1; // New
$quantity = 5;
$productCode = 'ABC123';
$images = array(
    'https://cdn.lookupbubbles.com/Content/images/bubbles-logo-for-home-page-betaWhite.png'
); // Set to an array of image URLs
$conditionDescription = null;
$size = 'Small'; // Or null
$colour = 'Red'; // Or null

$result = $bubbles->addBubble($title, $description, $categoryID, $price, $oldPrice, $weight, $conditionID, $conditionDescription, $quantity, $productCode, $size, $colour, $images);

$bubbleID = $result->AddBubbleResult;
echo "Created bubble http://bubbles-prod.waveaccess.ru:3450/Shop/Product/" . $bubbleID . "\r\n";
