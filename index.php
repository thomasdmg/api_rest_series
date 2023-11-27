<?php
require_once 'connexion.php';
require_once 'functions.php';


// Récupérer la méthode HTTP (GET, POST, PUT, DELETE)
// $retour_json = array();
$http_method = $_SERVER['REQUEST_METHOD'];

switch ($http_method) {
    
    case "POST":
        $jsonData = file_get_contents('php://input');
        
        if (isset($_GET['process'])) {

            if ($_GET['process'] == 'research' && !empty($jsonData)) {

                $data = json_decode($jsonData, true);
                $word_list = $data['word_list'];
                $count = count($word_list);
                $limit = isset($data['limit']) ? $data['limit'] : "";
                $sort = isset($data['sort']) ? $data['sort'] : "";
            
                // Exécute la requête pour chaque mot et stocke les résultats dans un tableau
                $result = array();
                for ($i = 0; $i < $count; $i++) {
                    $sql = $pdo->prepare(
                        "SELECT s.serie_id, s.nom_serie, IF(SUM(sm.`TF-IDF`) <> 0, SUM(sm.`TF-IDF`), SUM(sm.Ratio)) AS pertinence
                        FROM SeriesToMots sm 
                        INNER JOIN Series s ON sm.serie_id = s.serie_id 
                        WHERE 1
                        AND sm.mot_id = (SELECT m.mot_id FROM mots m WHERE m.keyword LIKE :keyword LIMIT 1) 
                        GROUP BY sm.serie_id, sm.mot_id"
                    );
                    $sql->bindParam(':keyword', $word_list[$i]);
                    $sql->execute();
                    $result[$i] = $sql->fetchAll(PDO::FETCH_ASSOC);
                }
            

                // Additionne les pertinences de chaque mot pour chaque série, multiplie par un poids et stocke les résultats dans un tableau
                $result2 = array();

                for ($i = 0; $i < $count; $i++) {
                    //Pondération différente pour chaque mot [1, 1/2, 1/3, 1/4, 1/5, ...]
                    $weight = 1 / ($i + 1);

                    foreach ($result[$i] as $value) {
                        $serieId = $value['serie_id'];
                        $pertinence = $value['pertinence'] * $weight;

                        if (array_key_exists($serieId, $result2)) {
                            $result2[$serieId]['pertinence'] += $pertinence;
                        } else {
                            $result2[$serieId] = $value;
                            $result2[$serieId]['pertinence'] = $pertinence;
                        }
                    }
                }
                
                // Si nom de la série dans la liste de keywords, on applique un bonus de 80% a la pertinence
                
                
                // Choix de l'ordre d'appariton des résultats
                if (isset($sort) && $sort == "1") {
                    usort($result2, function ($a, $b) {
                        return $a['pertinence'] <=> $b['pertinence'];
                    });
                } else if (isset($sort) && $sort == "-1") {
                    usort($result2, function ($a, $b) {
                        return $b['pertinence'] <=> $a['pertinence'];
                    });
                }

                // Limite le nombre de résultats 
                if (isset($limit) && $limit != "") {
                    $result2 = array_slice($result2, 0, $limit);
                }
            
                retour_json('200', true, "request executed", $result2);

            }elseif($_GET['process'] == 'add_feedback' && !empty($jsonData)){
                $data = json_decode($jsonData, true);
                // var_dump($data);
                if(isset($data['serie_id']) && $data['serie_id']!='' && isset($data['user_id']) && $data['user_id']!='' && isset($data['statut']) && $data['statut']!=''){

                    $sql_user_exist = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE 1 AND user_id = :user_id LIMIT 1");
                    $sql_user_exist->bindParam(':user_id', $data['user_id']);
                    $sql_user_exist->execute();
                    $result_user_exist = $sql_user_exist->fetch(PDO::FETCH_ASSOC);

                    $sql_serie_exist = $pdo->prepare("SELECT COUNT(*) as count FROM series WHERE 1 AND serie_id = :serie_id LIMIT 1");
                    $sql_serie_exist->bindParam(':serie_id', $data['serie_id']);
                    $sql_serie_exist->execute();
                    $result_serie_exist = $sql_serie_exist->fetch(PDO::FETCH_ASSOC);

                    // Si utilisateur & série existe :
                    if($result_user_exist['count'] == 1 && $result_serie_exist['count'] == 1 ){

                         //  Deux cas, soit user a déjà mis un feedback sur une série, soit il n'en a jamais mis :
                         $sql = $pdo->prepare("SELECT COUNT(*) as count FROM feedback WHERE 1 AND serie_id = :serie_id AND user_id = :user_id LIMIT 1");
                         $sql->bindParam(':serie_id', $data['serie_id']);
                         $sql->bindParam(':user_id', $data['user_id']);
                         $sql->execute();
                         $result = $sql->fetch(PDO::FETCH_ASSOC);
                         if($result['count'] == 0){
                             $sql_insert = $pdo->prepare("INSERT INTO feedback (serie_id, user_id, statut) VALUES (:serie_id, :user_id, :statut)");
                             $sql_insert->bindParam(':serie_id', $data['serie_id']);
                             $sql_insert->bindParam(':user_id', $data['user_id']);
                             $sql_insert->bindParam(':statut', $data['statut']);
                             $sql_insert->execute();
                             $result_feedback = $sql_insert->fetch(PDO::FETCH_ASSOC);
                             retour_json('200', true, "feedback inserted", null);
                         }elseif($result['count'] == 1){
                             $sql_update = $pdo->prepare("UPDATE feedback SET statut = :statut WHERE 1 AND serie_id = :serie_id AND user_id = :user_id");
                             $sql_update->bindParam(':serie_id', $data['serie_id']);
                             $sql_update->bindParam(':user_id', $data['user_id']);
                             $sql_update->bindParam(':statut', $data['statut']);
                             $sql_update->execute();
                             $result_feedback = $sql_update->fetch(PDO::FETCH_ASSOC);
                             retour_json('200', true, "feedback updated", null);
                         }else{
                             retour_json('400', false, "error in feedback process", null);
                         }

                    }elseif($result_user_exist['count'] == 0 && $result_serie_exist['count'] == 0 ){
                        retour_json('400', false, "user & serie doesn't exist, you can't insert a feedback", null);
                    }elseif($result_user_exist['count'] == 0){
                        retour_json('400', false, "user doesn't exist, you can't insert a feedback", null);
                    }elseif($result_serie_exist['count'] == 0){
                        retour_json('400', false, "serie doesn't exist, you can't insert a feedback", null);
                    }

                }

            }elseif ($_GET['process'] == 'get_recommendation_long' && !empty($jsonData)) {
                $data = json_decode($jsonData, true);
                isset($data['user_id']) ? $userId = $data['user_id'] : $userId = null;
                $sort = isset($data['sort']) ? $data['sort'] : "";
                $limit = isset($data['limit']) ? $data['limit'] : "";
                
                if ($userId != '' ) {
                    // Récupération des séries aimées par l'utilisateur
                    $likedSeriesQuery = $pdo->prepare("SELECT serie_id FROM feedback WHERE user_id = :user_id AND statut = 1");
                    $likedSeriesQuery->bindParam(':user_id', $userId);
                    $likedSeriesQuery->execute();
                    $likedSeries = $likedSeriesQuery->fetchAll(PDO::FETCH_ASSOC);

                    // Récupération des séries non-aimées par l'utilisateur
                    $dislikedSeriesQuery = $pdo->prepare("SELECT serie_id FROM feedback WHERE user_id = :user_id AND statut = -1");
                    $dislikedSeriesQuery->bindParam(':user_id', $userId);
                    $dislikedSeriesQuery->execute();
                    $dislikedSeries = $dislikedSeriesQuery->fetchAll(PDO::FETCH_ASSOC);
            
                    if (!empty($likedSeries) || !empty($dislikedSeries)) {

                        if(!empty($likedSeries)){

                            $likedSeriesIds = array();
                            foreach ($likedSeries as $series) {
                                $likedSeriesIds[] = $series['serie_id'];
                            }

                             //Pour chaque série aimée, on récupère les 25 premiers mots clés avec le plus gros score tf-idf
                            $likedSerieKeywords = array();
                            foreach ($likedSeriesIds as $serieId) {
                                $sql = $pdo->prepare(
                                    "SELECT m.mot_id
                                    FROM mots m
                                    INNER JOIN SeriesToMots sm ON m.mot_id = sm.mot_id
                                    WHERE sm.serie_id = :serie_id
                                    ORDER BY sm.`TF-IDF` DESC
                                    LIMIT 25"
                                );
                                $sql->bindParam(':serie_id', $serieId);
                                $sql->execute();
                                $result = $sql->fetchAll(PDO::FETCH_ASSOC);
                                $likedSerieKeywords[$serieId] = $result;
                            }

                            // Pour chaque série aimée, on récupère aussi leurs genres 
                            $likedSerieGenres = array();
                            foreach ($likedSeriesIds as $serieId) {
                                $sql = $pdo->prepare(
                                    "SELECT DISTINCT c.categorie_id
                                    FROM serietocategorie sc, series s, categorie c
                                    WHERE 1
                                    AND sc.serie_id = s.serie_id AND sc.categorie_id = c.categorie_id 
                                    AND s.serie_id = :serie_id
                                    "
                                );
                                $sql->bindParam(':serie_id', $serieId);
                                $sql->execute();
                                $result = $sql->fetchAll(PDO::FETCH_ASSOC);
                                $likedSerieGenres[$serieId] = $result;
                            }

                            // On rassemble tous les genres de toutes les séries likés dans un tableau unique
                            $uniqueGenres = array();
                            foreach ($likedSerieGenres as $serieGenres) {
                                foreach ($serieGenres as $genre) {
                                    $uniqueGenres[] = $genre['categorie_id'];
                                }
                            }
                            $uniqueGenres = array_unique($uniqueGenres);

                            // On rassemble tous les mots clés de toutes les séries likés dans un tableau unique
                            $allKeywords = array();
                            foreach ($likedSerieKeywords as $serieId => $keywords) {
                                foreach ($keywords as $keyword) {
                                    $allKeywords[] = $keyword['mot_id'];
                                }
                            }

                            // var_dump($allKeywords);

                            // Pour chaque série, on recherche parmis les séries qui ont le même genre $uniqueGenres, celles qui ont le score le plus élevé pour les mots clés $AllKeywords de la série
                            $recommendations = array();
                            foreach ($likedSeriesIds as $serieId) {
                                $sql = $pdo->prepare(
                                    "SELECT s.serie_id, s.nom_serie, SUM(sm.`TF-IDF`) AS pertinence
                                    FROM SeriesToMots sm 
                                    INNER JOIN Series s ON sm.serie_id = s.serie_id 
                                    INNER JOIN serietocategorie sc ON s.serie_id = sc.serie_id
                                    WHERE 1
                                    AND sm.mot_id IN (" . implode(',', $allKeywords) . ")
                                    AND s.serie_id <> :serie_id
                                    AND s.serie_id IN (
                                        SELECT s.serie_id
                                        FROM serietocategorie sc
                                        WHERE sc.categorie_id IN (" . implode(',', $uniqueGenres) . ")
                                    )
                                    GROUP BY sm.serie_id, sm.mot_id
                                    ORDER BY pertinence DESC
                                ");
                                $sql->bindParam(':serie_id', $serieId);
                                $sql->execute();
                                // var_dump($sql);
                                $result = $sql->fetchAll(PDO::FETCH_ASSOC);
                                // var_dump($result);
                                // Additonne les pertinences de chaque mot pour chaque série et stocke les résultats dans un tableau
                                $result2 = array();
                                foreach ($result as $value) {
                                    $serieId = $value['serie_id'];
                                    $pertinence = $value['pertinence'];

                                    if (array_key_exists($serieId, $result2)) {
                                        $result2[$serieId]['pertinence'] += $pertinence;
                                    } else {
                                        $result2[$serieId] = $value;
                                        $result2[$serieId]['pertinence'] = $pertinence;
                                    }
                                }

                                 // Si serie déjà aimée, on l'enlève des recommandations
                                foreach ($likedSeriesIds as $serieId) {    
                                    if (array_key_exists($serieId, $result2)) {
                                        unset($result2[$serieId]);
                                    }
                                }

                            }
                            $recommended_series = $result2;
                        }
                        if(!empty($dislikedSeries)){

                            $dislikedSeriesIds = array();
                            foreach ($dislikedSeries as $series) {
                                $dislikedSeriesIds[] = $series['serie_id'];
                            }

                             // Pour chaque série dislike, on récupère les 25 premiers mots clés avec le plus gros score tf-idf
                            $dislikedSerieKeywords = array();
                            foreach ($dislikedSeriesIds as $serieId) {
                                $sql = $pdo->prepare(
                                    "SELECT m.mot_id
                                    FROM mots m
                                    INNER JOIN SeriesToMots sm ON m.mot_id = sm.mot_id
                                    WHERE sm.serie_id = :serie_id
                                    ORDER BY sm.`TF-IDF` DESC
                                    LIMIT 10"
                                );
                                $sql->bindParam(':serie_id', $serieId);
                                $sql->execute();
                                $result = $sql->fetchAll(PDO::FETCH_ASSOC);
                                $dislikedSerieKeywords[$serieId] = $result;
                            }

                             // Pour chaque série dislike, on récupère aussi leurs genres
                            $dislikedSerieGenres = array();
                            foreach ($dislikedSeriesIds as $serieId) {
                                $sql = $pdo->prepare(
                                    "SELECT DISTINCT c.categorie_id
                                    FROM serietocategorie sc, series s, categorie c
                                    WHERE 1
                                    AND sc.serie_id = s.serie_id 
                                    AND sc.categorie_id = c.categorie_id 
                                    AND s.serie_id = :serie_id
                                    "
                                );
                                $sql->bindParam(':serie_id', $serieId);
                                $sql->execute();
                                $result = $sql->fetchAll(PDO::FETCH_ASSOC);
                                $dislikedSerieGenres[$serieId] = $result;
                            }

                            // On rassemble tous les genres de toutes les séries dislikés dans un tableau unique
                            $uniqueGenres2 = array();
                            foreach ($dislikedSerieGenres as $serieGenres) {
                                foreach ($serieGenres as $genre) {
                                    $uniqueGenres2[] = $genre['categorie_id'];
                                }
                            }

                            // On rassemble tous les mots clés de toutes les séries dislikés dans un tableau unique
                            $allKeywords2 = array();
                            foreach ($dislikedSerieKeywords as $serieId => $keywords) {
                                foreach ($keywords as $keyword) {
                                    $allKeywords2[] = $keyword['mot_id'];
                                }
                            }

                            // Pour chaque série, on recherche parmis les séries qui ont le même genre $uniqueGenres2, celles qui ont le score le plus élevé pour les mots clés $AllKeywords2 de la série
                            $sql = $pdo->prepare(
                                "SELECT s.serie_id, s.nom_serie, SUM(sm.`TF-IDF`) AS pertinence
                                FROM SeriesToMots sm 
                                INNER JOIN Series s ON sm.serie_id = s.serie_id 
                                INNER JOIN serietocategorie sc ON s.serie_id = sc.serie_id
                                WHERE 1
                                AND sm.mot_id IN (" . implode(',', $allKeywords2) . ")
                                AND s.serie_id <> :serie_id
                                AND s.serie_id IN (
                                    SELECT s.serie_id
                                    FROM serietocategorie sc
                                    WHERE sc.categorie_id IN (" . implode(',', $uniqueGenres2) . ")
                                )
                                GROUP BY sm.serie_id, sm.mot_id
                                ORDER BY pertinence DESC
                            ");
                            $sql->bindParam(':serie_id', $serieId);
                            $sql->execute();
                            $result = $sql->fetchAll(PDO::FETCH_ASSOC);

                            $unrecommend_series = $result;
                        }
                        
                        // ---------------------------------------------------------------------------------------------
                        
                        if(!empty($recommended_series)&&!empty($unrecommend_series)){
                            // Supprime les séries dislikées des séries recommandées si id est présent dans dislikedSeries
                            foreach ($recommended_series as $key => $value) {
                                foreach($dislikedSeries as $key2 => $value2){
                                    if($key == $value2['serie_id']){
                                        unset($recommended_series[$key]);
                                    }
                                }
                            }
                        
                            // Parcours les séries recommandées et non recommandées et si une série est dans les deux tableaux, on applique un malus a la pertinence des séries recommandées
                            foreach ($recommended_series as $key => $value) {

                                foreach ($unrecommend_series as $key2 => $value2) {

                                    if($key == $key2){
                                        $recommended_series[$key]['pertinence'] = $recommended_series[$key]['pertinence'] * 0.6;
                                    }

                                }

                            }
                            $recommended_series = sort_result($recommended_series, $sort, $limit);
                            retour_json('200', true, "request executed", $recommended_series);

                        }else if(!empty($recommended_series)&&empty($unrecommend_series)){

                            $recommended_series = sort_result($recommended_series, $sort, $limit);
                            retour_json('200', true, "request executed", $recommended_series);

                        }else if(empty($recommended_series)&&!empty($unrecommend_series)){

                            $unrecommend_series = sort_result($unrecommend_series, '1', $limit);
                            retour_json('200', true, "request executed", $unrecommend_series);

                        }else if(empty($recommended_series)&&empty($unrecommend_series)){
                            retour_json('400', false, "no recommendation, function not implemented yet", null);
                        }
                        
                    }  
                }
            
            }elseif ($_GET['process'] == 'get_recommendation_lite' && !empty($jsonData)) {
                $data = json_decode($jsonData, true);
                isset($data['user_id']) ? $userId = $data['user_id'] : $userId = null;
                $sort = isset($data['sort']) ? $data['sort'] : "";
                $limit = isset($data['limit']) ? $data['limit'] : "";
            
                if ($userId != '') {
                    // Récupération des séries aimées par l'utilisateur
                    $likedSeriesQuery = $pdo->prepare("SELECT serie_id FROM feedback WHERE user_id = :user_id AND statut = 1");
                    $likedSeriesQuery->bindParam(':user_id', $userId);
                    $likedSeriesQuery->execute();
                    $likedSeries = $likedSeriesQuery->fetchAll(PDO::FETCH_ASSOC);

                    // Récupération des séries non-aimées par l'utilisateur
                    $dislikedSeriesQuery = $pdo->prepare("SELECT serie_id FROM feedback WHERE user_id = :user_id AND statut = -1");
                    $dislikedSeriesQuery->bindParam(':user_id', $userId);
                    $dislikedSeriesQuery->execute();
                    $dislikedSeries = $dislikedSeriesQuery->fetchAll(PDO::FETCH_ASSOC);
            
                    if (!empty($likedSeries) || !empty($dislikedSeries)) {

                        if(!empty($likedSeries)){

                            $likedSeriesIds = array();
                            foreach ($likedSeries as $series) {
                                // Converti les id en int
                                $likedSeriesIds[] = intval($series['serie_id']);
                            }

                            // On récupère les mots les plus pertinents de toutes les séries likés :
                            $likedSerieKeywords = array();
                            $ids = implode(',', $likedSeriesIds);
                            // var_dump($ids);
                            
                            $sql = $pdo->prepare(
                                "SELECT m.mot_id, SUM(sm.`TF-IDF`) AS pertinence
                                FROM seriestomots sm
                                INNER JOIN mots m ON sm.mot_id = m.mot_id
                                WHERE 1 
                                AND sm.serie_id IN (:LikedSeriesIds)
                                GROUP BY sm.mot_id
                                ORDER BY pertinence DESC
                                LIMIT 100");
                            $sql->bindParam(':LikedSeriesIds', $ids);
                            $sql->execute();
                            $result = $sql->fetchAll(PDO::FETCH_ASSOC);
                            
                            // var_dump($result);
                            $allKeywords = array();
                            foreach ($result as $keyword) {
                                $allKeywords[] = $keyword['mot_id'];
                            }

                            // Pour chaque série aimée, on récupère aussi leurs genres 
                            $likedSerieGenres = array();
                            foreach ($likedSeriesIds as $serieId) {
                                $sql = $pdo->prepare(
                                    "SELECT DISTINCT c.categorie_id
                                    FROM serietocategorie sc, series s, categorie c
                                    WHERE 1
                                    AND sc.serie_id = s.serie_id AND sc.categorie_id = c.categorie_id 
                                    AND s.serie_id = :serie_id
                                    "
                                );
                                $sql->bindParam(':serie_id', $serieId);
                                $sql->execute();
                                $result = $sql->fetchAll(PDO::FETCH_ASSOC);
                                $likedSerieGenres[$serieId] = $result;
                            }

                            // On rassemble tous les genres de toutes les séries likés dans un tableau unique
                            $uniqueGenres = array();
                            foreach ($likedSerieGenres as $serieGenres) {
                                foreach ($serieGenres as $genre) {
                                    $uniqueGenres[] = $genre['categorie_id'];
                                }
                            }
                            $uniqueGenres = array_unique($uniqueGenres);
                            // var_dump($allKeywords);

                            // Pour chaque série, on recherche parmis les séries qui ont le même genre $uniqueGenres, celles qui ont le score le plus élevé pour les mots clés $AllKeywords de la série
                            $recommendations = array();
                            foreach ($likedSeriesIds as $serieId) {
                                $sql = $pdo->prepare(
                                    "SELECT s.serie_id, s.nom_serie, SUM(sm.`TF-IDF`) AS pertinence
                                    FROM SeriesToMots sm 
                                    INNER JOIN Series s ON sm.serie_id = s.serie_id 
                                    INNER JOIN serietocategorie sc ON s.serie_id = sc.serie_id
                                    WHERE 1
                                    AND sm.mot_id IN (" . implode(',', $allKeywords) . ")
                                    AND s.serie_id <> :serie_id
                                    AND s.serie_id IN (
                                        SELECT s.serie_id
                                        FROM serietocategorie sc
                                        WHERE sc.categorie_id IN (" . implode(',', $uniqueGenres) . ")
                                    )
                                    GROUP BY sm.serie_id, sm.mot_id
                                    ORDER BY pertinence DESC
                                ");
                                $sql->bindParam(':serie_id', $serieId);
                                $sql->execute();
                                // var_dump($sql);
                                $result = $sql->fetchAll(PDO::FETCH_ASSOC);
                                // var_dump($result);
                                // Additonne les pertinences de chaque mot pour chaque série et stocke les résultats dans un tableau
                                $result2 = array();
                                foreach ($result as $value) {
                                    $serieId = $value['serie_id'];
                                    $pertinence = $value['pertinence'];

                                    if (array_key_exists($serieId, $result2)) {
                                        $result2[$serieId]['pertinence'] += $pertinence;
                                    } else {
                                        $result2[$serieId] = $value;
                                        $result2[$serieId]['pertinence'] = $pertinence;
                                    }
                                }

                                 // Si serie déjà aimée, on l'enlève des recommandations
                                foreach ($likedSeriesIds as $serieId) {    
                                    if (array_key_exists($serieId, $result2)) {
                                        unset($result2[$serieId]);
                                    }
                                }

                            }
                            $recommended_series = $result2;
                        }
                        if(!empty($dislikedSeries)){

                            $dislikedSeriesIds = array();
                            foreach ($dislikedSeries as $series) {
                                $dislikedSeriesIds[] = $series['serie_id'];
                            }

                             // Pour chaque série dislike, on récupère les 25 premiers mots clés avec le plus gros score tf-idf
                            $dislikedSerieKeywords = array();
                            $ids = implode(',', $dislikedSeriesIds);

                            $sql = $pdo->prepare(
                                "SELECT m.mot_id, SUM(sm.`TF-IDF`) AS pertinence
                                FROM seriestomots sm
                                INNER JOIN mots m ON sm.mot_id = m.mot_id
                                WHERE 1 
                                AND sm.serie_id IN (:dislikedSeriesIds)
                                GROUP BY sm.mot_id
                                ORDER BY pertinence DESC
                                LIMIT 100");
                            $sql->bindParam(':dislikedSeriesIds', $ids);
                            $sql->execute();
                            $result = $sql->fetchAll(PDO::FETCH_ASSOC);

                            $allKeywords2 = array();
                            foreach ($result as $keyword) {
                                $allKeywords2[] = $keyword['mot_id'];
                            }

                             // Pour chaque série dislike, on récupère aussi leurs genres
                            $dislikedSerieGenres = array();
                            foreach ($dislikedSeriesIds as $serieId) {
                                $sql = $pdo->prepare(
                                    "SELECT DISTINCT c.categorie_id
                                    FROM serietocategorie sc, series s, categorie c
                                    WHERE 1
                                    AND sc.serie_id = s.serie_id 
                                    AND sc.categorie_id = c.categorie_id 
                                    AND s.serie_id = :serie_id
                                    "
                                );
                                $sql->bindParam(':serie_id', $serieId);
                                $sql->execute();
                                $result = $sql->fetchAll(PDO::FETCH_ASSOC);
                                $dislikedSerieGenres[$serieId] = $result;
                            }

                            // On rassemble tous les genres de toutes les séries dislikés dans un tableau unique
                            $uniqueGenres2 = array();
                            foreach ($dislikedSerieGenres as $serieGenres) {
                                foreach ($serieGenres as $genre) {
                                    $uniqueGenres2[] = $genre['categorie_id'];
                                }
                            }

                            // Pour chaque série, on recherche parmis les séries qui ont le même genre $uniqueGenres2, celles qui ont le score le plus élevé pour les mots clés $AllKeywords2 de la série
                            $sql = $pdo->prepare(
                                "SELECT s.serie_id, s.nom_serie, SUM(sm.`TF-IDF`) AS pertinence
                                FROM SeriesToMots sm 
                                INNER JOIN Series s ON sm.serie_id = s.serie_id 
                                INNER JOIN serietocategorie sc ON s.serie_id = sc.serie_id
                                WHERE 1
                                AND sm.mot_id IN (" . implode(',', $allKeywords2) . ")
                                AND s.serie_id <> :serie_id
                                AND s.serie_id IN (
                                    SELECT s.serie_id
                                    FROM serietocategorie sc
                                    WHERE sc.categorie_id IN (" . implode(',', $uniqueGenres2) . ")
                                )
                                GROUP BY sm.serie_id, sm.mot_id
                                ORDER BY pertinence DESC
                            ");
                            $sql->bindParam(':serie_id', $serieId);
                            $sql->execute();
                            $result = $sql->fetchAll(PDO::FETCH_ASSOC);

                            $unrecommend_series = $result;
                        }
                        
                        // ---------------------------------------------------------------------------------------------
                        
                        if(!empty($recommended_series)&&!empty($unrecommend_series)){

                            // Supprime les séries dislikées des séries recommandées si id est présent dans dislikedSeries
                            foreach ($recommended_series as $key => $value) {
                                foreach($dislikedSeries as $key2 => $value2){
                                    if($key == $value2['serie_id']){
                                        unset($recommended_series[$key]);
                                    }
                                }
                            }
                        
                            // Parcours les séries recommandées et non recommandées et si une série est dans les deux tableaux, on applique un malus a la pertinence des séries recommandées
                            foreach ($recommended_series as $key => $value) {

                                foreach ($unrecommend_series as $key2 => $value2) {

                                    if($key == $key2){
                                        $recommended_series[$key]['pertinence'] = $recommended_series[$key]['pertinence'] * 0.6;
                                    }

                                }

                            }
                            $recommended_series = sort_result($recommended_series, $sort, $limit);
                            retour_json('200', true, "request executed", $recommended_series);

                        }else if(!empty($recommended_series)&&empty($unrecommend_series)){

                            $recommended_series = sort_result($recommended_series, $sort, $limit);
                            retour_json('200', true, "request executed", $recommended_series);

                        }else if(empty($recommended_series)&&!empty($unrecommend_series)){

                            $unrecommend_series = sort_result($unrecommend_series, '1', $limit);
                            retour_json('200', true, "request executed", $unrecommend_series);

                        }else if(empty($recommended_series)&&empty($unrecommend_series)){
                            retour_json('400', false, "no recommendation, function not implemented yet", null);  // On appel les recommendations générales si l'utilisateur n'a pas de feedback
                        }
                        
                    }  
                }
            
            }elseif($_GET['process'] == 'get_popular'){

                // récupère les séries avec le plus de like
                // isset($jsonData) ? $limit=$jsonData['limit'] : "";
                isset($jsonData) ? $data = json_decode($jsonData, true) : "";

                $sql = $pdo->prepare(
                    "SELECT s.serie_id, s.nom_serie, COUNT(f.statut) AS nb_like
                    FROM series s
                    INNER JOIN feedback f ON s.serie_id = f.serie_id
                    WHERE f.statut = 1
                    GROUP BY s.serie_id
                    ORDER BY nb_like DESC, s.Nom_serie ASC
                    "
                );
                $sql->execute();
                $result = $sql->fetchAll(PDO::FETCH_ASSOC);
                isset($data) && $data['limit'] != '' ? $result = array_slice($result, 0, $data['limit']) : "";
                retour_json('200', true, "request executed", $result);          
                
                          
            }elseif($_GET['process'] == 'get_unpopular'){
                // récupère les séries avec le plus de dislike
                // isset($jsonData) ? $limit=$jsonData['limit'] : "";
                isset($jsonData) ? $data = json_decode($jsonData, true) : "";

                $sql = $pdo->prepare(
                    "SELECT s.serie_id, s.nom_serie, COUNT(f.statut) AS nb_dislike
                    FROM series s
                    INNER JOIN feedback f ON s.serie_id = f.serie_id
                    WHERE f.statut = -1
                    GROUP BY s.serie_id
                    ORDER BY nb_dislike, s.nom_serie DESC
                    "
                );
                $sql->execute();
                $result = $sql->fetchAll(PDO::FETCH_ASSOC);
                isset($data) && $data['limit'] != '' ? $result = array_slice($result, 0, $data['limit']) : "";
                retour_json('200', true, "request executed", $result);

            }elseif($_GET['process'] == 'get_like_of_user'){   

                // récupère les séries liké par un utilisateur
                // isset($jsonData) ? $limit=$jsonData['limit'] : "";
                isset($jsonData) ? $data = json_decode($jsonData, true) : "";

                $sql = $pdo->prepare(
                    "SELECT s.serie_id, s.nom_serie
                    FROM series s
                    INNER JOIN feedback f ON s.serie_id = f.serie_id
                    WHERE f.statut = 1
                    AND f.user_id = :user_id
                    ORDER BY s.nom_serie ASC
                    "
                );

                $sql->bindParam(':user_id', $data['user_id']);
                $sql->execute();
                $result = $sql->fetchAll(PDO::FETCH_ASSOC);
                isset($data) && $data['limit'] != '' ? $result = array_slice($result, 0, $data['limit']) : "";
                retour_json('200', true, "request executed", $result);

            }elseif($_GET['process'] == 'get_dislike_of_user'){
                // récupère les séries disliké par un utilisateur
                // isset($jsonData) ? $limit=$jsonData['limit'] : "";
                isset($jsonData) ? $data = json_decode($jsonData, true) : "";

                $sql = $pdo->prepare(
                    "SELECT s.serie_id, s.nom_serie
                    FROM series s
                    INNER JOIN feedback f ON s.serie_id = f.serie_id
                    WHERE f.statut = -1
                    AND f.user_id = :user_id
                    ORDER BY s.nom_serie ASC
                    "
                );

                $sql->bindParam(':user_id', $data['user_id']);
                $sql->execute();
                $result = $sql->fetchAll(PDO::FETCH_ASSOC);
                isset($data) && $data['limit'] != '' ? $result = array_slice($result, 0, $data['limit']) : "";
                retour_json('200', true, "request executed", $result);

            }elseif($_GET['process'] == 'get_info_serie'){

                isset($jsonData) ? $data = json_decode($jsonData, true) : "";

                if(isset($data['serie_id']) && $data['serie_id'] != ''){
                    $sql = $pdo->prepare(
                        "SELECT s.serie_id, s.nom_serie, s.desc
                        FROM series s
                        WHERE s.serie_id = :serie_id
                        LIMIT 1;"
                    );
                    $sql->bindParam(':serie_id', $data['serie_id']);
                    $sql->execute();
                    $result = $sql->fetch(PDO::FETCH_ASSOC);

                    $sql2 = $pdo->prepare(
                        "SELECT c.nom
                        FROM categorie c INNER JOIN serieToCategorie sc ON c.categorie_id = sc.categorie_id
                        WHERE sc.serie_id = :serie_id"
                    );
                    $sql2->bindParam(':serie_id', $data['serie_id']);
                    $sql2->execute();
                    $result2 = $sql2->fetchAll(PDO::FETCH_ASSOC);

                    $result2 = array_column($result2, 'nom');
                    $result['categories'] = $result2;
                    retour_json('200', true, "request executed", $result);
                }else{
                    retour_json('400', false, "please, specify number of serie", null);
                }
            }else{
                retour_json('400', false, "please, chose a validate process for POST method", null);
            }
            
        } else {
            retour_json('400', false, "please, chose a process", null);
        }

    break;

    default:
        retour_json('400', false, "this HTTP request methode is not supported", null);
    break;
}
?>