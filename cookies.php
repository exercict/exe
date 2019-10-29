<?php
if(!empty($_COOKIE['n'])){
    echo 'Кука уже установлена: '.$_COOKIE['n'];
}else{
    setcookie('n', 'Hello', time() + 3600);
    echo 'Добавлена новая печенька';
};
?>