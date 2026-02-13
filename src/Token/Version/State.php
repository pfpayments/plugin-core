<?php

declare(strict_types=1);

namespace PostFinanceCheckout\PluginCore\Token\Version;

use PostFinanceCheckout\PluginCore\State\ValidatesStateTransitions;

enum State: string {
    use ValidatesStateTransitions;

    case UNINITIALIZED = 'UNINITIALIZED';
    case ACTIVE = 'ACTIVE';
    case OBSOLETE = 'OBSOLETE';

    public static function getTransitionMap(): array
    {
        return [
            'initial' => [
                self::UNINITIALIZED->value,
            ],
            'transitions' => [
                self::UNINITIALIZED->value => [self::ACTIVE->value, self::OBSOLETE->value],
                self::ACTIVE->value => [self::OBSOLETE->value],
            ],
            'final' => [
                self::OBSOLETE->value,
            ],
            'any_to' => [
                self::OBSOLETE->value,
            ],
            'sequence' => [
                self::UNINITIALIZED->value,
                self::ACTIVE->value,
            ],
        ];
    }
}
