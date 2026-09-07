<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';require_roles(['admin']);if($_SERVER['REQUEST_METHOD']!=='POST')redirect('/pages/users.php');verify_csrf();
$id=(int)($_POST['id']??0);$name=post_string('name','')??'';$email=mb_strtolower(post_string('email','')??'');$role=post_string('role');$active=(int)($_POST['is_active']??0)===1?1:0;$password=post_string('password','')??'';
if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($role,['admin','patron','employe'],true)){flash('error','Vérifiez le nom, l’e-mail et le rôle.');redirect('/pages/users.php'.($id?'?id='.$id:''));}
if((!$id||$password!=='')&&mb_strlen($password)<10){flash('error','Le mot de passe doit contenir au moins 10 caractères.');redirect('/pages/users.php'.($id?'?id='.$id:''));}
if($id===(int)current_user()['id']&&!$active){flash('error','Vous ne pouvez pas désactiver votre propre compte.');redirect('/pages/users.php?id='.$id);}
$pdo=db();try{if($id){if($password!==''){$s=$pdo->prepare('UPDATE users SET name=?,email=?,role=?,is_active=?,password_hash=?,updated_at=NOW() WHERE id=?');$s->execute([$name,$email,$role,$active,password_hash($password,PASSWORD_DEFAULT),$id]);}else{$s=$pdo->prepare('UPDATE users SET name=?,email=?,role=?,is_active=?,updated_at=NOW() WHERE id=?');$s->execute([$name,$email,$role,$active,$id]);}log_action($pdo,'user',$id,'modification_utilisateur',$name.' · '.$role);flash('success','Le compte a été mis à jour.');}else{$s=$pdo->prepare('INSERT INTO users(name,email,password_hash,role,is_active) VALUES(?,?,?,?,?)');$s->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$role,$active]);$id=(int)$pdo->lastInsertId();log_action($pdo,'user',$id,'creation_utilisateur',$name.' · '.$role);flash('success','Le compte utilisateur a été créé.');}}catch(PDOException $e){flash('error',$e->getCode()==='23000'?'Cette adresse e-mail est déjà utilisée.':'Le compte n’a pas pu être enregistré.');}redirect('/pages/users.php');
