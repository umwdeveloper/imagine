<?php

// Set your OpenAI API key here
$apiKey = 'YOUR_API_KEY';

// Function to generate image using ChatGPT-4
function generateImage($prompt, $apiKey) {
    // Check if the prompt is empty
    if (empty($prompt)) {
        return json_encode(array('error' => 'Prompt is empty.'));
    }

    // API endpoint
    $url = 'https://api.openai.com/v1/images/generations';

    // Data to send to the API
    $data = array(
        'model' => 'dall-e-3', // ChatGPT-4 model
        'prompt' => $prompt,
        'size' => "1024x1024",
        'quality' => "standard",
        'n' => 1,
    );

    // Set up cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ));

    // Execute the request
    $response = curl_exec($ch);

    // Check for errors
    if ($response === false) {
        return json_encode(array('error' => 'Error: ' . curl_error($ch)));
    }

    // Close cURL session
    curl_close($ch);

    // Decode the JSON response
    $result = json_decode($response, true);

    // Check if there's an error in the response
    if (!empty($result['error'])) {
        return json_encode(array('error' => $result['error']['message']));
    }

    // Extract the generated image URL from the response
    $imageURL = !empty($result['data'][0]['url']) ? $result['data'][0]['url'] : '';

    // Return the image URL
    return json_encode(array('imageURL' => $imageURL));
}

$prompt = "An angry cat drinking milk";
echo generateImage($prompt, $apiKey);
