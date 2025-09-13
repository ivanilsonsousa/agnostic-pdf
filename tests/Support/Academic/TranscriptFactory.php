<?php

declare(strict_types=1);

namespace Tests\Support\Academic;

use Faker\Factory as FakerFactory;
use Faker\Generator;

final class TranscriptFactory
{
  private Generator $faker;

  /** @var string[] */
  private array $disciplinasBase = [
    'Algoritmos e Programação',
    'Estruturas de Dados',
    'Banco de Dados',
    'Engenharia de Software',
    'Programação Web',
    'Arquitetura de Computadores',
    'Redes de Computadores',
    'Sistemas Operacionais',
    'Probabilidade e Estatística',
    'Cálculo Diferencial e Integral',
    'Inteligência Artificial',
    'Segurança da Informação',
    'Compiladores',
    'Interação Humano-Computador',
    'Paradigmas de Programação',
  ];

  public function __construct(?Generator $faker = null)
  {
    $this->faker = $faker ?? FakerFactory::create('pt_BR');
  }

  public function makeData(): array
  {
    $headerFactory = new CertificateFactory($this->faker);
    $header        = $headerFactory->makeData();

    $qtd         = $this->faker->numberBetween(10, 14);
    $disciplinas = [];

    for ($i = 0; $i < $qtd; $i++) {
      $nome         = $this->faker->randomElement($this->disciplinasBase);
      $creditos     = $this->faker->numberBetween(2, 6);
      $cargaHoraria = $creditos * 20;
      $nota         = round($this->faker->randomFloat(2, 5.0, 10.0), 2);
      $resultado    = $nota >= 6.0 ? 'Aprovado' : 'Reprovado';

      $disciplinas[] = [
        'codigo'        => sprintf('ACM-%03d', $this->faker->numberBetween(1, 999)),
        'nome'          => $nome,
        'creditos'      => $creditos,
        'carga_horaria' => $cargaHoraria,
        'nota'          => $nota,
        'resultado'     => $resultado,
        'periodo'       => $this->faker->randomElement(['2023.1', '2023.2', '2024.1', '2024.2', '2025.1']),
      ];
    }

    return array_merge($header, ['disciplinas' => $disciplinas]);
  }
}
