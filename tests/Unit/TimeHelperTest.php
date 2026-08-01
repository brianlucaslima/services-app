<?php

use App\Brain\Helpers\TimeHelper;

test('it converts decimal hours to human readable format', function () {
    expect(TimeHelper::decimalToHuman(2.5))->toBe('2h 30m');
    expect(TimeHelper::decimalToHuman(100.5))->toBe('100h 30m');
    expect(TimeHelper::decimalToHuman(100))->toBe('100h');
    expect(TimeHelper::decimalToHuman(0))->toBe('0h');
    expect(TimeHelper::decimalToHuman(null))->toBe('0h');
});

test('it parses human readable values to decimal hours', function () {
    expect(TimeHelper::humanToDecimal('2:30'))->toBe(2.5);
    expect(TimeHelper::humanToDecimal('100:30'))->toBe(100.5);
    expect(TimeHelper::humanToDecimal('200'))->toBe(200.0);
    expect(TimeHelper::humanToDecimal('200:00'))->toBe(200.0);
    expect(TimeHelper::humanToDecimal(''))->toBe(0.0);
    expect(TimeHelper::humanToDecimal(null))->toBe(0.0);
});

test('it converts decimal hours to colon format', function () {
    expect(TimeHelper::decimalToColon(2.5))->toBe('02:30');
    expect(TimeHelper::decimalToColon(100.5))->toBe('100:30');
    expect(TimeHelper::decimalToColon(200))->toBe('200:00');
    expect(TimeHelper::decimalToColon(0))->toBe('00:00');
    expect(TimeHelper::decimalToColon(null))->toBe('00:00');
});
