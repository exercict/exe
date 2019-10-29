<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Пример работы ГЕО таргетинга</title>
<link rel="stylesheet" href="css/geo.css">

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
<script charset="utf-8" src="https://api-maps.yandex.ru/1.1/index.xml" type="text/javascript"></script>
<script>
    $(document).ready(function() {
        if (YMaps.location.region != '') {
            var youCity = (YMaps.location.city);
            if (youCity === "Ижевск"){
                $('.contact p span').text('8 (3412) 956-801');
            }
            else if (youCity === "Казань"){
                $('.contact p span').text('8 (843) 231-80-00');
            }
            else {
                $('.contact p span').text('8(800) 100-24-24 бесплатно по РФ');
                $('.city span').text('Другой город')
            }
        }
        document.cookie = "city=" + youCity;
    });
</script>
</head>
<body>
	<div class="header">
		<div class="logo">
		    <h1>Smartlanding</h1>
		</div>
       
        <div class="contact">
            <p>Т.: <span></span></p>
        </div>
	</div>
    <section class="you">
    <h2>Ваше текущее положение, для примера</h2>
        <ul>
            <li class="country"> Ваша страна: <span></span></li>
            <li class="region">Ваш регион (область): <span></span></li>
            <li class="city">Ваш город: <span>
                <?php
                echo $_COOKIE['city'];
                ?>
            </span></li>
            <p class="nothing"></p>
            <div><?php
                echo $_COOKIE['yandex_login'];
            ?></div>
        </ul>
    </section>
    
    
<script type="text/javascript">
$(document).ready(function() {
if (YMaps.location.city != '') {
	$('.country span').html(YMaps.location.country);
	$('.region span').html(YMaps.location.region);
	$('.city span').html(YMaps.location.city);
	
}
else {
	$('.nothing').html(' ');
	$('.nothing').html('Яндекс не знает где вы :)');
}
});

</script>

</body>
</html>