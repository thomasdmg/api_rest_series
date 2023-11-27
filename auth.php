<?php
require_once 'connexion.php';
require_once 'functions.php';

$http_method = $_SERVER['REQUEST_METHOD'];
switch ($http_method) {

    case "POST":
        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);

        if (isset($_GET['process']) && $_GET['process'] == 'login' && isset($data['user']) && isset($data['password'])) {
            $user = $data['user'];
            $password = $data['password'];
        
            $sql = $pdo->prepare("SELECT COUNT(*) as is_user, user_id, username FROM users WHERE username = :user AND password = :password LIMIT 1");
            $sql->bindParam(':user', $user);
            $sql->bindParam(':password', $password);
            $sql->execute();
            $result = $sql->fetchAll(PDO::FETCH_ASSOC);
            
            if ($result[0]['is_user'] == 1) {

                $username = $result[0]['username'];
                $user_id = $result[0]['user_id'];
                $retour = array(
                    'success' => true, 
                    'user_id' => $user_id,
                    'username' => $username
                );
                echo json_encode($retour);

            } else {

                $retour = array(
                    'success' => 'false',
                );
                echo json_encode($retour);
            }

        } else {
            retour_json(400, false, "Paramètres manquants", null);
        }
    break;

    default:
        retour_json(400, false, "Méthode non autorisée", null);
    break;
}


?>