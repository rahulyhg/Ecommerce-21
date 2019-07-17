<?php

use \Hcode\PageAdmin;
use \Hcode\Model\User;
use \Hcode\Model\Product;

$app->get("/admin/products", function() {

	User::verifyLogin();

	$product = Product::listAll();

	$page = new PageAdmin();

	$page->setTpl("products", [
		"products" => $product
	]);

});

$app->get("/admin/products/create", function() {

	User::verifyLogin();

	$product = Product::listAll();

	$page = new PageAdmin();

	$page->setTpl("products-create");

});

$app->post("/admin/products/create", function() {

	User::verifyLogin();

	$product = new Product();

	$product->setData($_POST);

	var_dump($_POST);

	$product->save();

	header("Location: /admin/products");
	exit;

});

$app->get("/admin/products/:idproduct/delete", function($idproduct) {

	User::verifyLogin();


	$product = new Product();

	$product->get((int)$idproduct);

	$product->delete();

	header("Location: /admin/products");

	exit;

});

$app->get("/admin/products/:idproduct", function($idproduct) {

	User::verifyLogin();

	$page = new PageAdmin();

	$product = new Product();

	$product->get((int)$idproduct);

	$page->setTpl("products-update", [
		"product" => $product->getValues()
	]);


});

$app->post("/admin/products/:idproduct", function($idproduct) {

	User::verifyLogin();

	$product = new Product();

	$product->get((int)$idproduct);

	$product->setData($_POST);

	$product->save();

	$product->setPhoto($_FILES["file"]);

	header("Location: /admin/products");
	exit;

});
