<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    /** @var list<string> */
    private static array $nicknames = [
        'Warriors', 'Lakers', 'Bulls', 'Celtics', 'Heat',
        'Nets', 'Suns', 'Bucks', 'Nuggets', 'Clippers',
        'Spurs', 'Mavericks', 'Rockets', 'Thunder', 'Pistons',
    ];

    /** @var list<string> */
    private static array $cities = [
        'Golden State', 'Los Angeles', 'Chicago', 'Boston', 'Miami',
        'Brooklyn', 'Phoenix', 'Milwaukee', 'Denver', 'Sacramento',
        'San Antonio', 'Dallas', 'Houston', 'Oklahoma City', 'Detroit',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $index    = $this->faker->unique()->numberBetween(0, count(self::$cities) - 1);
        $city     = self::$cities[$index];
        $nickname = self::$nicknames[$index];

        return [
            'code'      => strtoupper($this->faker->unique()->lexify('???')),
            'name'      => "{$city} {$nickname}",
            'logo_path' => null,
            'is_active' => true,
        ];
    }
}
