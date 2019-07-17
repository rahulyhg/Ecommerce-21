<?php

function formatPrice($vlprice = .0) {

   return number_format($vlprice, 2, ",", ".");
    
}