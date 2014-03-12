<?php

define('BUBBLES_API_WSDL', 'https://www.lookupbubbles.com/API/BubblesIntegrationService.svc?wsdl');
define('BUBBLES_API_TEST_WSDL', 'http://bubbles-prod.waveaccess.ru:3450/API/BubblesIntegrationService.svc?wsdl');
define('BUBBLES_API_ENDPOINT', 'https://www.lookupbubbles.com/API/BubblesIntegrationService.svc/soap');
define('BUBBLES_API_TEST_ENDPOINT', 'http://bubbles-prod.waveaccess.ru:3450/API/BubblesIntegrationService.svc/soaptest');

class Bubbles {

    private $apiUsername = null;

    private $apiPassword = null;

    private $soapClient = null;

    private $logFile = null;

    private $logID = null;

    private $testMode = false;

    public function __construct ($apiUsername, $apiPassword, $logFile = null, $testMode = false) {
        $this->apiUsername = $apiUsername;
        $this->apiPassword = $apiPassword;
        $this->logFile = $logFile;
        $this->testMode = $testMode;
    }

    private function getClient () {
        if (is_null($this->soapClient)) {
            //USE WS-SECURITY ,WE SET USERNAME AND PASSWORD
            $ns = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
            $token = new stdClass;
            $token->Username = new SOAPVar($this->apiUsername, XSD_STRING, null, null, null, $ns);
            $token->Password = new SOAPVar($this->apiPassword, XSD_STRING, null, null, null, $ns);

            $wsec = new stdClass;
            $wsec->UsernameToken = new SoapVar($token, SOAP_ENC_OBJECT, null, null, null, $ns);

            $headers = new SOAPHeader($ns, 'Security', $wsec, true);

            if ($this->testMode) {
                $this->soapClient = new SoapClient(BUBBLES_API_TEST_WSDL, array('location' => BUBBLES_API_TEST_ENDPOINT));;
            } else {
                $this->soapClient = new SoapClient(BUBBLES_API_WSDL);
            }
            $this->soapClient->__setSOAPHeaders($headers);
        }

        return $this->soapClient;
    }

    public function log ($message, $data = null) {
        if (!is_null($this->logFile)) {
            if (is_null($this->logID)) {
                $this->logID = rand(1000,9999);
            }

            // Open and append the trace file
            if(file_exists($this->logFile))
            {
                // See if the trace file needs rotating
                if(filesize($this->logFile) > (500 * 1048576))
                {
                    $secondary = $this->logFile . '.1';
                    if(file_exists($secondary))
                    {
                        unlink($secondary);
                    }
                    rename($this->logFile, $secondary);
                }
            }

            $time = date("d-m-Y H:i:s");
            $remote_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown';
            $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'Unknown';

            $message = '[' . $time . '] ' . $this->logID . ' | ' . $remote_address . ' | ' . $script . ' | ' . $message . " | " . (is_null($data) ? '' : json_encode($data)) . "\r\n";

            $fp = fopen($this->logFile, 'a');
            fwrite($fp, $message);
            fclose($fp);
        }
    }

    public function getCategories ($extended = true) {
        $params = array();
        if ($extended) {
            $params['extended'] = 'true';
        }

        $response = $this->getClient()->GetCategoriesDictionary($params);
        $categories = array();
        $cats = $response->GetCategoriesDictionaryResult->KeyValueOflongstring;

        foreach ($cats as $cat) {
            $categories[$cat->Key] = $cat->Value;
        }

        return $categories;
    }

    public function getSpecifications ($categoryID) {
        $response = $this->getClient()->GetSpecificationsDictionary(array(
            'categoryID' => $categoryID
        ));
        $specifications = array();
        if (isset($response->GetSpecificationsDictionaryResult->KeyValueOflongstring)) {
            $specs = $response->GetSpecificationsDictionaryResult->KeyValueOflongstring;

            foreach ($specs as $spec) {
                $specifications[$spec->Key] = $spec->Value;

            }
        }

        return $specifications;
    }

    public function getConditions () {
        $response = $this->getClient()->GetConditionsDictionary();

        $conditions = array();
        $conds = $response->GetConditionsDictionaryResult->KeyValueOflongstring;

        foreach ($conds as $cond) {
            $conditions[$cond->Key] = $cond->Value;
        }

        return $conditions;
    }

    public function addBubble ($title, $description, $categoryID, $price, $oldPrice, $weight, $conditionID, $conditionDescription, $quantity, $productCode, $size, $colour, $imageURLs) {
        $data = array(
            'Title' => $title,
            'Description' => $description,
            'CategoryID' => $categoryID,
            'Price' => $price,
            'Weight' => $weight,
            'ConditionID' => $conditionID,
            'Quantity' => $quantity
        );
        if (!is_null($oldPrice)) { $data['OldPrice'] = $oldPrice; }
        if (!is_null($conditionDescription)) { $data['ConditionDescription'] = $conditionDescription; }
        if (!is_null($productCode)) { $data['ProductCode'] = $productCode; }
        if (!is_null($size)) { $data['Size'] = $size; }
        if (!is_null($colour)) { $data['Colour'] = $colour; }
        if (!is_null($imageURLs)) { $data['ImagesURL'] = $imageURLs; }

        $this->log('AddBubble', array(
            'Title' => $title,
            'CategoryID' => $categoryID,
            'Price' => $price,
            'Weight' => $weight,
            'ConditionID' => $conditionID,
            'Quantity' => $quantity
        ));

        return $this->getClient()->AddBubble(array('addBubbleRequest' => $data));
    }

    public function addBubbleVariation ($id, $price, $oldPrice, $productCode, $quantity, $imageURLs, $size, $colour) {
        $data = array(
            'ID' => $id,
            'BubbleVariation' => array(
                'Price' => $price
            )
        );
        if (!is_null($oldPrice)) { $data['BubbleVariation']['OldPrice'] = $oldPrice; }
        if (!is_null($productCode)) { $data['BubbleVariation']['ProductCode'] = $productCode; }
        if (!is_null($quantity)) { $data['BubbleVariation']['Quantity'] = $quantity; }
        if (!is_null($imageURLs)) { $data['BubbleVariation']['ImagesURL'] = $imageURLs; }
        if (!is_null($size)) { $data['BubbleVariation']['Size'] = $size; }
        if (!is_null($colour)) { $data['BubbleVariation']['Colour'] = $colour; }

        $this->log('AddBubbleVariation', $data);

        return $this->getClient()->AddBubbleVariation(array('request' => $data));
    }

    private function getVariationLabel ($selections, $key, $value) {
        for ($i = 0; $i < count($selections); $i += 1) {
            if ($selections[$i]['key'] == $key) {
                $options = $selections[$i]['options'];
                // Found the key, so now look up the option
                for ($j = 0; $j < count($options); $j += 1) {
                    if ($options[$j]['value'] == $value) {
                        return $options[$j]['label'];
                    }
                }
            }
        }
        return null;
    }

    public function addBubbleWithVariations ($title, $description, $categoryID, $weight, $conditionID, $conditionDescription, $variations) {
        $data = array(
            'Title' => $title,
            'Description' => $description,
            'CategoryID' => $categoryID,
            'Weight' => $weight,
            'ConditionID' => $conditionID,
            'BubbleVariations' => array()
        );

        if (!is_null($conditionDescription)) {
            $data['ConditionDescription'] = $conditionDescription;
        }

        // Go through each variation and add it
        for ($i = 0; $i < count($variations['items']); $i += 1) {
            $item = $variations['items'][$i];
            $size = null;
            $colour = null;

            // Ignore items that are zero, or have an option other than "size" and "colour"
            $allowedOptions = true;
            foreach ($item['options'] as $key => $value) {
                if ($key == 'size') {
                    $size = $this->getVariationLabel($variations['selections'], $key, $value);
                    //$size = $option['valueLabel'];
                } elseif ($key == 'colour') {
                    $colour = $this->getVariationLabel($variations['selections'], $key, $value);
                    //$colour = $option['valueLabel'];
                } else {
                    $allowedOptions = false;
                    break;
                }
            }

            if ($item['price'] > 0 && $allowedOptions) {
                $variationData = array(
                    'Price' => $item['price'],
                    'Quantity' => $item['stock']
                );

                if (isset($item['listPrice']) && !is_null($item['listPrice'])) {
                    $oldPrice = $item['listPrice'];
                    if ($oldPrice > $item['price']) {
                        $variationData['OldPrice'] = $oldPrice;
                    }
                }

                if (!is_null($item['code']) && $item['code'] !== '') {
                    $variationData['ProductCode'] = $item['code'];
                }

                if (!is_null($size)) {
                    $variationData['Size'] = $size;
                }

                if (!is_null($colour)) {
                    $variationData['Colour'] = $colour;
                }

                if (count($item['images']) > 0) {
                    $variationData['ImagesURL'] = $item['images'];
                }

                $data['BubbleVariations'][] = $variationData;
            }
        }

        if (count($data['BubbleVariations']) > 0) {
            $this->log('AddBubbleWithVariations', array(
                'Title' => $data['Title'],
                'CategoryID' => $data['CategoryID'],
                'Weight' => $data['Weight'],
                'ConditionID' => $data['ConditionID'],
                'BubbleVariations' => $data['BubbleVariations']
            ));
            return $this->getClient()->AddBubbleWithVariations(array('request' => $data));
        } else {
            return false;
        }

    }

    public function updateBubble ($id, $title, $description, $categoryID, $price, $oldPrice, $weight, $conditionID, $conditionDescription, $quantity, $productCode, $size, $colour, $imageURLs, $variations) {
        if (is_null($variations) || count($variations['items']) == 0) {
            $selections = array();
            $options = array();

            if ($size !== null) {
                $selections[] = array(
                    'key' => 'size',
                    'label' => 'Size',
                    'options' => array(
                        'value' => $size,
                        'code' => null,
                        'label' => $size
                    )
                );

                $options['size'] = $size;
            }

            if ($colour !== null) {
                $selections[] = array(
                    'key' => 'colour',
                    'label' => 'Colour',
                    'options' => array(
                        'value' => $colour,
                        'code' => null,
                        'label' => $colour
                    )
                );

                $options['colour'] = $colour;
            }

            $item = array(
                'options' => $options,
                'stock' => $quantity,
                'price' => $price,
                'listPrice' => $oldPrice,
                'code' => $productCode,
                'images' => $imageURLs
            );

            // Create a single variation from the base product information as we'll just merge this later
            $variations = array('items' => array($item), 'selections' => $selections);
        }

        $this->log('Local variations for update', $variations);

        // Get the existing bubble with variations
        $bubble = $this->getBubbleWithVariationsByID($id);

        // If the bubbles variations isn't an array, then make it one (because single items don't return arrays)
        $bubbleVariations = $bubble->GetBubbleWithVariationsByIDResult->BubbleVariations->BubbleVariationDataObject;
        if (!is_array($bubbleVariations)) {
            $bubbleVariations = array($bubbleVariations);
        }

        $this->log('Remote variations for update', $bubbleVariations);

        // Synchronise the variations

        // Keep track of bubbles we want to keep (default to keeping none)
        $keepBubbles = array();
        for ($i = 0; $i < count($bubbleVariations); $i += 1) {
            $keepBubbles[$bubbleVariations[$i]->ID] = array(
                'keep' => false,
                'isPaused' => $bubbleVariations[$i]->IsPaused == 1
            );
        }

        // Update all matching variations
        foreach ($variations['items'] as $item) {
            $size = null;
            $colour = null;

            // We only support size and colour variations if they are set
            $allowedOptions = true;
            foreach ($item['options'] as $key => $value) {
                if ($key == 'size') {
                    $size = $this->getVariationLabel($variations['selections'], $key, $value);
                    //$size = $option['valueLabel'];
                } elseif ($key == 'colour') {
                    $colour = $this->getVariationLabel($variations['selections'], $key, $value);
                    //$colour = $option['valueLabel'];
                } else {
                    $allowedOptions = false;
                    break;
                }
            }

            if ($allowedOptions) {
                // See if we can find a match for this variation
                $found = false;
                foreach ($bubbleVariations as $bubbleVariation) {
                    $bubbleSize = isset($bubbleVariation->Size) ? $bubbleVariation->Size : null;
                    $bubbleColour = isset($bubbleVariation->Colour) ? $bubbleVariation->Colour : null;

                    if ($bubbleSize == '') {
                        $bubbleSize = null;
                    }

                    if ($bubbleColour == '') {
                        $bubbleColour = null;
                    }

                    //$this->log('Testing variation', array('BubbleSize' => $bubbleSize, 'VariationSize' => $size, 'BubbleColour' => $bubbleColour, 'Colour' => $colour));
                    // See if they are all equal
                    if ($bubbleSize == $size && $bubbleColour == $colour) {
                        $found = true;
                        // We have a match, so lets update it - if the new price is zero, then pause it
                        if ($item['price'] > 0) {
                            $keepBubbles[$bubbleVariation->ID]['keep'] = true; // Keep it
                            // Now lets update the bubble variation

                            $this->UpdateBubbleVariationInfoByID($bubbleVariation->ID, $size, $colour, $item['code']);

                            // Update the stock and price
                            if (!is_null($item['listPrice']) && $item['listPrice'] <= $item['price']) {
                                $item['listPrice'] = null;
                            }
                            $this->updateBubbleStockInfoByID($bubbleVariation->ID, $item['price'], $item['stock'], $item['listPrice']);

                            // Remove all the images and add them again, but only if there is a change
                            if (isset($bubbleVariation->ImagesData) && isset($bubbleVariation->ImagesData->ImageDataObject)) {
                                $bubbleImages = $bubbleVariation->ImagesData->ImageDataObject;
                                if (!is_array($bubbleImages)) {
                                    $bubbleImages = array($bubbleImages);
                                }
                            } else {
                                $bubbleImages = array();
                            }

                            $resetImages = false;
                            if (count($bubbleImages) != count($item['images'])) {
                                $resetImages = true;
                            } else {
                                // We have the same number of images, let see if they have been changed
                                for ($i = 0; $i < count($bubbleImages); $i += 1) {
                                    if ($bubbleImages[$i]->OriginalUrl != $item['images'][$i]) {
                                        $resetImages = true;
                                        break;
                                    }
                                }
                            }

                            if ($resetImages) {
                                // Remove all the existing images
                                for ($i = 0; $i < count($bubbleImages); $i += 1) {
                                    $this->removeBubbleImageByID($bubbleImages[$i]->ID);
                                }

                                if (count($item['images']) > 0) {
                                    // Add all the images again
                                    $this->addBubbleImageByID($bubbleVariation->ID, $item['images']);
                                }
                            }

                            // If it's paused, relist it
                            if ($bubbleVariation->IsPaused == 1) {
                                $this->relistBubbleByID($bubbleVariation->ID);
                            }
                        } else {
                            $this->log('Not keeping because there is no variation price', array('price' => $item['price'], 'size' => $size, 'colour' => $colour));
                        }
                        break;
                    }
                }

                if (!$found) {
                    $this->log('Adding variation', array('size' => $size, 'colour' => $colour, 'price' => $item['price']));
                    // We couldn't find it, so lets add the new variation
                    if ($item['price'] > 0) {
                        if (!is_null($item['listPrice']) && $item['listPrice'] <= $item['price']) {
                            $item['listPrice'] = null;
                        }

                        $this->addBubbleVariation ($id, $item['price'], $item['listPrice'], $item['code'], $item['stock'], $item['images'], $size, $colour);
                    }
                }
            }
        }

        // Pause all non-matching variations
        foreach ($keepBubbles as $id => $data) {
            if ($data['keep'] == false && $data['isPaused'] == false) {
                try {
                    $this->log('Removing variation as we don\'t want to keep it', array('id' => $id));
                    $this->pauseBubbleVariationByID($id);
                } catch (Exception $ex) {
                    // Don't worry about failure to pause, it could already be paused
                }
            }
        }

        // Update the common info for the bubble
        $this->updateBubbleCommonInfoByID($id, $title, $description, null, $weight, $conditionID, $conditionDescription, null, null);

        // Update the category
        $this->updateBubbleCategoryAndSpecificationsByID($id, $categoryID);
    }

    public function getBubbleByID ($id) {
        return $this->getClient()->GetBubbleByID(array('id' => $id));
    }

    public function getBubbleWithVariationsByID ($id) {
        return $this->getClient()->GetBubbleWithVariationsByID(array('id' => $id));
    }

    public function addBubbleImageByID ($id, $imageURLs) {
        $this->log('AddBubbleImageByID - ' . $id . ' [' . implode(',', $imageURLs) . ']');
        return $this->getClient()->AddBubbleImageByID(array('addBubbleImageRequest' => array(
            'ID' => $id,
            'ImagesURL' => $imageURLs
        )));
    }

    public function updateBubbleCommonInfoByID ($id, $title, $description, $productCode, $weight, $conditionID, $conditionDescription, $size, $colour) {
        $data = array();
        if (!is_null($title)) { $data['Title'] = $title; }
        if (!is_null($description)) { $data['Description'] = $description; }
        if (!is_null($productCode)) { $data['ProductCode'] = $productCode; }
        if (!is_null($weight)) { $data['Weight'] = $weight; }
        if (!is_null($conditionID)) { $data['ConditionID'] = $conditionID; }
        if (!is_null($conditionDescription)) { $data['ConditionDescription'] = $conditionDescription; }
        if (!is_null($size)) { $data['Size'] = $size; }
        if (!is_null($colour)) { $data['Colour'] = $colour; }

        $message = array(
            'ID' => $id,
            'BubbleCommonInfo' => $data
        );

        $logData = array();
        foreach ($data as $key => $value) {
            if ($key != 'Description') {
                $logData[$key] = $value;
            }
        }

        $this->log('UpdateBubbleCommonInfoByID', array(
            'ID' => $id,
            'BubbleCommonInfo' => $logData
        ));

        return $this->getClient()->UpdateBubbleCommonInfoByID(array(
            'bubbleCommonInfoRequest' => $message
        ));
    }



    public function updateBubbleVariationInfoByID ($id, $size, $colour, $productCode) {
        $data = array();
        if (!is_null($productCode)) { $data['ProductCode'] = $productCode; }
        if (!is_null($size)) { $data['Size'] = $size; }
        if (!is_null($colour)) { $data['Colour'] = $colour; }

        $message = array(
            'ID' => $id,
            'BubbleVariationInfo' => $data
        );

        $this->log('UpdateBubbleVariationInfoByID', $message);

        return $this->getClient()->UpdateBubbleVariationInfoByID(array(
            'request' => $message
        ));
    }


    public function updateBubbleStockInfoByID ($id, $price, $quantity, $oldPrice) {
        $data = array();
        if (!is_null($price)) { $data['Price'] = $price; }
        if (!is_null($quantity)) { $data['Quantity'] = $quantity; }
        if (!is_null($oldPrice)) { $data['OldPrice'] = $oldPrice; }

        $message = array(
            'ID' => $id,
            'BubblePriceAndQuantity' => $data
        );

        $this->log('UpdateBubbleStockInfoByID', $message);

        return $this->getClient()->UpdateBubbleStockInfoByID(array(
            'bubblePriceAndQuantityRequest' => $message
        ));
    }

    public function updateBubbleCategoryAndSpecificationsByID ($id, $categoryID, $specifications = null) {


        $catSpecData = array(
            'CategoryID' => $categoryID
        );

        if (!is_null($specifications)) {
            $specs = array();
            foreach ($specifications as $specID => $specValue) {
                $specs[] = array(
                    'SpecificationID' => $specID,
                    'Value' => $specValue
                );
            }

            $catSpecData['Specifications'] = $specs;
        }

        $message = array(
            'ID' => $id,
            'CategoryAndSpecifications' => $catSpecData
        );

        $this->log('UpdateBubbleCategoryAndSpecificationsByID', $message);

        $data = array(
            'bubbleCategoryAndSpecificationsRequest' => $message
        );

        return $this->getClient()->UpdateBubbleCategoryAndSpecificationsByID($data);
    }

    public function relistBubbleByID ($id) {
        $this->log('RelistBubbleByID - ' . $id);
        return $this->getClient()->RelistBubbleByID(array(
            'id' => $id
        ));
    }

    public function pauseBubbleByID ($id) {
        // Get the bubble with variation
        $bubble = $this->getBubbleWithVariationsByID($id);
        $bubbleVariations = $bubble->GetBubbleWithVariationsByIDResult->BubbleVariations->BubbleVariationDataObject;
        if (!is_array($bubbleVariations)) {
            $bubbleVariations = array($bubbleVariations);
        }

    $this->log('Pausing ' . $id . ' with ' . count($bubbleVariations) . ' variation(s)');

        for ($i = 0; $i < count($bubbleVariations); $i += 1) {
            if ($bubbleVariations[$i]->IsPaused != 1) {
                // We can pause it
                try {
                    $this->pauseBubbleVariationByID($bubbleVariations[$i]->ID);
                } catch (Exception $ex) {
                    $this->log('Error pausing bubble ' . $bubbleVariations[$i]->ID . ': ' . $ex->getMessage());
                }
            } else {
                $this->log('Bubble ' . $bubbleVariations[$i]->ID . ' is already paused');
            }
        }
    }

    public function pauseBubbleVariationByID ($id) {
        $this->log('pauseBubbleVariationByID - ' . $id);
        return $this->getClient()->PauseBubbleByID(array(
            'id' => $id
        ));
    }

    public function removeBubbleByID ($id) {
        // Get the bubble with variation
        $bubble = $this->getBubbleWithVariationsByID($id);
        $bubbleVariations = $bubble->GetBubbleWithVariationsByIDResult->BubbleVariations->BubbleVariationDataObject;
        if (!is_array($bubbleVariations)) {
            $bubbleVariations = array($bubbleVariations);
        }

        for ($i = 0; $i < count($bubbleVariations); $i += 1) {
            if ($bubbleVariations[$i]->IsPaused != 1) {
                // We must pause it first
                $this->pauseBubbleVariationByID($bubbleVariations[$i]->ID);
            }
            $this->removeBubbleVariationByID($bubbleVariations[$i]->ID);
        }
    }

    public function removeBubbleVariationByID ($id) {
        $this->log('RemoveBubbleByID - ' . $id);
        return $this->getClient()->RemoveBubbleByID(array(
            'id' => $id
        ));
    }

    public function removeBubbleImageByID ($id) {
        $this->log('RemoveImage - ' . $id);
        return $this->getClient()->RemoveImage(array(
            'request' => array(
                'ImageID' => $id
            )
        ));
    }

    public function UpdatePrimaryImage ($id) {
        $this->log('UpdatePrimaryImage - ' . $id);
        return $this->getClient()->UpdatePrimaryImage(array(
            'request' => array(
                'ImageID' => $id
            )
        ));
    }

    public function getBubbleList ($startIndex = 0, $limit = 100) {
        return $this->getClient()->GetBubbleList(array('skip' => $startIndex, 'take' => $limit));
    }

    public function getBubbleWithVariationsList ($startIndex = 0, $limit = 100) {
        return $this->getClient()->GetBubbleWithVariationsList(array('skip' => $startIndex, 'take' => $limit));
    }
}