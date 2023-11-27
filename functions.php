<?php

// Formatage de la r�ponse API en JSON
function retour_json($http_response, $success, $message, $result = null) {

    $response = array(
        'http_response' => $http_response,
        'success' => $success,
        'message' => $message,
        'response' => $result
    );
    echo(json_encode($response));
    exit;
}

// Trie des r�sultats
function sort_result($array, $sort=false, $limit=false){
    // Choix de l'ordre d'appariton des r�sultats
    if (isset($sort) && $sort == "1") {
        usort($array, function ($a, $b) {
            return $a['pertinence'] <=> $b['pertinence'];
        });
    } else if (isset($sort) && $sort == "-1") {
        usort($array, function ($a, $b) {
            return $b['pertinence'] <=> $a['pertinence'];
        });
    }
    // Choix de la limite du nombre de r�sultats
    if (isset($limit) && $limit != "") {
        $array = array_slice($array, 0, $limit);
    }
    // var_dump($sort);
    return $array;
}

?>