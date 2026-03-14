<?php

it('returns healthy status', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', 'healthy')
        ->assertJsonPath('data.database', true);
});

it('lists registered sources', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'sources' => [
                    '*' => ['name', 'identifier', 'configured'],
                ],
            ],
        ]);
});
