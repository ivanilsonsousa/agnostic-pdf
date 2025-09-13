<?php

declare(strict_types=1);

namespace Tests\Support\Academic;

use Faker\Factory as FakerFactory;
use Faker\Generator;

final class CertificateFactory
{
  private Generator $faker;

  public function __construct(?Generator $faker = null)
  {
    $this->faker = $faker ?? FakerFactory::create('pt_BR');
  }

  public function makeData(): array
  {
    $aluno = $this->faker->name();
    $curso = $this->faker->randomElement([
      'Sistemas de Informação',
      'Ciência da Computação',
      'Engenharia de Software',
      'Redes de Computadores',
      'Análise e Desenvolvimento de Sistemas',
    ]);

    $cpf    = method_exists($this->faker, 'cpf') ? $this->faker->cpf(false) : $this->faker->numerify('###########');
    $titulo = $this->faker->randomElement(['Bacharel', 'Tecnólogo']);

    return [
      'instituicao' => 'ACME Instituto de Tecnologia',
      'aluno'       => $aluno,
      'cpf'         => $cpf,
      'curso'       => $curso,
      'titulo'      => $titulo,
      'codigo'      => strtoupper($this->faker->bothify('ACME-########-????')),
    ];
  }
}
