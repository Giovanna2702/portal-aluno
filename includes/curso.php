<?php

function dataLiberacaoModulo(string $dataMatricula, int $numeroModulo): DateTimeImmutable
{
    if ($numeroModulo < 1) {
        throw new InvalidArgumentException('O número do módulo deve ser maior ou igual a 1.');
    }

    $inicio = DateTimeImmutable::createFromFormat('!Y-m-d', $dataMatricula);
    $erros = DateTimeImmutable::getLastErrors();

    if (!$inicio || ($erros !== false && ($erros['warning_count'] > 0 || $erros['error_count'] > 0))) {
        throw new InvalidArgumentException('Data de matrícula inválida.');
    }

    $meses = $numeroModulo - 1;
    $diaOriginal = (int) $inicio->format('d');

    $primeiroDiaDestino = $inicio
        ->modify('first day of this month')
        ->modify('+' . $meses . ' months');

    $ultimoDiaDestino = (int) $primeiroDiaDestino->format('t');
    $diaDestino = min($diaOriginal, $ultimoDiaDestino);

    return $primeiroDiaDestino->setDate(
        (int) $primeiroDiaDestino->format('Y'),
        (int) $primeiroDiaDestino->format('m'),
        $diaDestino
    );
}

function numeroMaximoModuloLiberado(?string $dataMatricula, ?DateTimeImmutable $agora = null): int
{
    if (!$dataMatricula) {
        return 1;
    }

    $agora = $agora ?? new DateTimeImmutable('today');
    $maximo = 1;

    try {
        for ($numero = 2; $numero <= 6; $numero++) {
            if ($agora >= dataLiberacaoModulo($dataMatricula, $numero)) {
                $maximo = $numero;
                continue;
            }

            break;
        }
    } catch (InvalidArgumentException $e) {
        return 1;
    }

    return $maximo;
}

function moduloEstaLiberado(int $numeroModulo, ?string $dataMatricula, ?DateTimeImmutable $agora = null): bool
{
    if ($numeroModulo < 1 || $numeroModulo > 6) {
        return false;
    }

    return $numeroModulo <= numeroMaximoModuloLiberado($dataMatricula, $agora);
}
