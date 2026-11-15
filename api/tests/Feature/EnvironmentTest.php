<?php

it('runs the suite in the testing environment', function () {
    expect(app()->environment('testing'))->toBeTrue()
        ->and(app()->runningUnitTests())->toBeTrue();
});
