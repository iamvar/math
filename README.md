# BcParser

Every developer knows: float is not the best choice for precise calculation.

```bash
php > var_dump(1.2 * 3);
float(3.5999999999999996)
```

We have bcmath, you say.

Yes, but calculating small expression, like `($a + $b * $c - $d) / $e` becomes as heavy as

```php
<?php

$result = bcdiv(bcsub(bcadd($a, bcmul($b, $c)), $d), $e);
```

That is where BcParser tool becomes useful:

With registered helpers functions, you can just use

```php
<?php

$result = calc("$a + $b * $c - $d");
```

That's it!

All calculations behind the scene use bcmath functions.

```bash
php > var_dump(calc("1.2 * 3"));
string("3.6")
```

## Helper Functions

To register global helper functions, just update your composer.json with

```
"autoload": {
    "files": [
        "vendor/iamvar/bcparser/src/helpers.php"
    ]
},
```

## bcscale

All calculations are performed with scale, taken by default from bcscale().   
Do not forget to set required bcscale in your bootstrap file.

```php
<?php

bcscale(25);
```

## Usage

By default `calc` function cuts trailing zeroes,  
e.g with scale = 4 `calc("0.1 + 0.9")` will return `"1"`

If you want to save scale, add second parameter as false  
`calc("0.1 + 0.9", false)` will return `"1.0000"`

## License

BcParser is licensed under the [MIT License](LICENSE).