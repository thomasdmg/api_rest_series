# api_rest_series
API for searching and recommending series based on a user's preferences. Database generated from SRT files

REQU�TES HTTP AUTORISEES : 

POST :

login :
http://localhost/api_rest_series/auth.php?process=login 

{
    "user":"username",
    "password":"password"
}   

recherche :

http://localhost/api_rest_series/index.php?process=research

{
    "word_list": ["arme","police","etc..."],
    "sort": -1,  <!-- -1 => DESC, 1 => ASC --> 
    "limit": 10  <!-- limite le nombre de r�sultats --> 
}

r�cup�rer des recommendations : 

<!-- Get_recommendation_lite : Meilleures performances sur un grand nombre de s�ries mais l�g�rement moins pertinent --> 
http://localhost/api_rest_series/index.php?process=get_recommendation_lite

{
    "user_id":3, <!-- N� de l'utilisateur --> 
    "limit":10, <!-- limite le nombre de r�sultats --> 
    "sort":-1 <!-- -1 => DESC, 1 => ASC --> 
}

<!-- Get_recommendation_long : Meilleures recommendations mais plus lent sur un grand nombre de s�ries --> 
http://localhost/api_rest_series/index.php?process=get_recommendation_long

{
    "user_id":3, <!-- id de l'user --> 
    "limit":10, <!-- limite le nombre de r�sultats --> 
    "sort":-1 <!-- -1 => DESC, 1 => ASC --> 
}

<!-- add_feeback : ajoute un like ou dislike pour un utilisateur donn� --> 
http://localhost/api_rest_series/index.php?process=add_feedback

{
    "serie_id":"73", <!-- N� de la s�rie --> 
    "user_id":"3", <!-- N� de l'utilisateur --> 
    "statut":"-1" <!-- Statut : -1 => DISLIKE, 1 => LIKE, 0 => AUCUN --> 
}

<!-- get_like_of_user : R�cup�re les s�ries lik� par un utilisateur --> 
http://localhost/api_rest_series/index.php?process=get_like_of_user

{
    "user_id":3, <!-- N� de l'utilisateur --> 
    "limit":10 <!-- limite le nombre de r�sultats --> 
}

<!-- get_dislike_of_user : R�cup�re les s�ries dislik� par un utilisateur --> 
http://localhost/api_rest_series/index.php?process=get_dislike_of_user

{
    "user_id":3,
    "limit":10
}