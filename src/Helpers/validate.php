<?php

namespace App\Helpers;

use Symfony\Component\Validator\Validator\ValidatorInterface;

class Validator
{

  public function __construct(private ValidatorInterface $validatorInterface)
  {
  }

  public function validateUser($user)
  {
    $errors = $this->validatorInterface->validate($user);

    if (count($errors) > 0) {
      return [
        "success" => false,
        "message" => "Une erreur s'est produite lors de la création du compte.",
        "errors" => array_map(static function ($error) {
          return [
            "champs" => $error->getPropertyPath(),
            "message" => $error->getMessage()
          ];
        }, iterator_to_array($errors))
      ];
    }
  }
}
