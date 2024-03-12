<?php

namespace App\Services;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

class SendEmail
{

  private $mailerInterface;

  public function __construct(MailerInterface $mailerInterface,)
  {
    $this->mailerInterface = $mailerInterface;
  }

  public function send($user, $token)
  {

    try {
      $email = (new TemplatedEmail())
        ->from('lapetiteboutiquedephee@gmail.com')
        ->to($user->getEmail())
        ->subject('Vérification de l\'adresse mail')
        ->html("<p>Hello {$user->getFirstname()},</p>"
          . "<p>Merci de votre inscription sur La Petite Boutique D'Ephée !</p>"
          . "<p>S'il vous plait, cliquez sur le lien pour vérifier votre adresse mail:</p>"
          . "<p><a href='https://127.0.0.1:8000/api/auth/confirm-email/" . $token . "'>"
          . "Activer mon compte</a>"
          . "</p>"
          . "<p>A plus tard sur la Petite Boutique D'Ephée !</p>");

      $this->mailerInterface->send($email);
    } catch (TransportExceptionInterface $e) {
      return $e;
    }
  }
}
