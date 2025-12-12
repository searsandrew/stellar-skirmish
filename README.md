### Stellar Skirmish — Core Game Engine (PHP)

Stellar Skirmish is a lightweight PHP library that powers a fast, head‑to‑head card skirmish over a deck of planets. Players secretly choose a ship strength each battle; the highest card wins and claims all planets currently in the pot. Ties escalate the stakes by adding another planet to the pot for the next battle. Cards are one‑use; when everyone runs out, the game ends and the player with the most victory points (VP) from claimed planets wins.

This repository contains the pure game engine: immutable data objects, state transitions, and simple serialization helpers — no I/O, UI, or framework coupling.

#### Requirements
- PHP 8.3+
- Composer

Install via Composer:
```
composer require jamrfun/stellar-skirmish
```

Import namespace:
```
use StellarSkirmish\{GameEngine, GameConfig, GameState, Planet};
```

---

### What the game is

- Two or more players compete to claim planets worth 1–3 VP.
- Each round, at least one planet is face‑up “in the pot.”
- Every player simultaneously chooses a ship card (an integer strength). In code, you submit these sequentially and the engine resolves when all have played.
- Highest single card wins the battle and takes all planets in the pot.
- If there is a tie for highest, nobody scores; another planet is added to the pot (if any remain), and a new battle begins. Stakes accumulate until a future battle produces a single winner.
- Cards are discarded when played (win or lose). The game ends when all player hands are empty; most VP wins.

Notes on current engine behavior:
- If a tie occurs and the planet deck is empty, no additional planet is added. The pot remains for the next battle. The game still ends when all hands are empty.

---

### Key features

- Deterministic setup option with seeds (for reproducible tests/simulations)
- Simple planet model with optional classes and abilities
- Battle resolution with escalating pot on ties
- Compact serialization for `GameState` and `Planet`
- Convenience helpers for UI/API (pot summary and totals)

---

### How to start a game

You can use a standard, shuffled, two‑player configuration, or build your own.

```
use StellarSkirmish\{GameEngine, GameConfig};

$engine = new GameEngine();

// Option A: standard two‑player setup (shuffled default deck, ships 1..15)
$config = GameConfig::standardTwoPlayer();

// Option B: custom setup
// $config = new GameConfig(
//     playerCount: 3,
//     planets: myCustomPlanets(),   // array of Planet
//     fleetValues: range(1, 12),    // per‑player ship values
// );

$state = $engine->startNewGame($config);

// $state now contains per‑player hands, planet deck, empty pot, etc.
```

Planet helpers:
- `Planet::defaultDeck()` returns a simple 15‑planet deck (5×1 VP, 5×2 VP, 5×3 VP).
- You can create custom planets via `new Planet(id: 'P1', victoryPoints: 2, name: 'Rigel')`.

Deterministic shuffles and presets:
- `GameConfig::standardTwoPlayer(?int $seed = null)` creates a two‑player setup with the default deck and ship values `1..15`. If you pass a seed, the planet deck will be shuffled deterministically.
- You can also pass `seed` directly to `new GameConfig(...)` to deterministically shuffle your custom deck.

---

### How to play a card

Submit a player’s chosen ship strength using `GameEngine::playCard`. The engine records the play, and when all players have played for the current battle, it resolves the battle (awarding the pot to the winner or escalating on a tie).

```
use StellarSkirmish\{GameEngine, GameConfig};

$engine = new GameEngine();
$state  = $engine->startNewGame(GameConfig::standardTwoPlayer());

// Battle 1
$state = $engine->playCard($state, playerId: 1, cardValue: 5);
$state = $engine->playCard($state, playerId: 2, cardValue: 3);

// After this call, the battle resolves: player 1 claims the planet(s) in the pot

// Next battle
$state = $engine->playCard($state, playerId: 1, cardValue: 2);
$state = $engine->playCard($state, playerId: 2, cardValue: 2); // tie → add another planet to the pot
```

Exceptions you may encounter:
- `StellarSkirmish\Exceptions\InvalidMove` — the player doesn’t hold the specified card.
- `StellarSkirmish\Exceptions\GameOver` — you attempted to play after the game ended.

Detect game end and compute scores:
```
if ($engine->isGameOver($state)) {
    $scores = $state->scores(); // [playerId => total VP]
}
```

---

### How to serialize/restore state

`GameState` provides `toArray()` and `fromArray()` for storage (e.g., DB JSON, cache, message bus). `Planet` is nested and also serializes cleanly.

```
use StellarSkirmish\GameState;

// Serialize to JSON (for DB, cache, etc.)
$payload = json_encode($state->toArray(), JSON_THROW_ON_ERROR);

// ... store $payload ... later restore:

$array    = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
$restored = GameState::fromArray($array);

// $restored is a full GameState instance (including nested Planet objects)
```

Structure of the serialized array (keys):
- `player_count` (int)
- `hands` (array<int, int[]>)
- `planet_deck` (Planet[] as arrays)
- `current_planet_index` (int)
- `planet_pot` (Planet[] as arrays)
- `claimed_planets` (array<int, Planet[] as arrays>)
- `current_plays` (array<int, int|null>)
- `game_over` (bool)
- `end_reason` (string|null)

---

### Minimal end‑to‑end example

```
use StellarSkirmish\{GameEngine, GameConfig};

$engine = new GameEngine();
$state  = $engine->startNewGame(GameConfig::standardTwoPlayer());

// Round 1
$state = $engine->playCard($state, 1, 7);
$state = $engine->playCard($state, 2, 4); // resolves, P1 takes pot

// Round 2 (tie, stakes escalate)
$state = $engine->playCard($state, 1, 3);
$state = $engine->playCard($state, 2, 3); // tie → add another planet to pot

// Round 3
$state = $engine->playCard($state, 1, 9);
$state = $engine->playCard($state, 2, 6); // resolves, P1 takes all planets in pot

if ($engine->isGameOver($state)) {
    $scores = $state->scores();
}

// Persist
$json = json_encode($state->toArray(), JSON_THROW_ON_ERROR);

// Restore later
$restored = GameState::fromArray(json_decode($json, true, flags: JSON_THROW_ON_ERROR));
```

---

### Planet classes and abilities

Planets can optionally have a class and one or more abilities. Abilities are resolved when a planet is claimed. This keeps the engine deterministic and easy to test.

Types available today:
- `PlanetAbilityType::DoubleNextPlanetNoCombat` — when claimed, immediately add the next planet from the deck to the pot and award it to the same player without a battle (if a next planet exists).
- `PlanetAbilityType::ClassSetBonus` — scaffolded for future set‑collection scoring; tests verify shape, not scoring yet.

Modeling a planet with an ability:
```
use StellarSkirmish\{Planet, PlanetClass, PlanetAbility, PlanetAbilityType};

$triggerWorld = new Planet(
    id: 'P1',
    victoryPoints: 1,
    name: 'Trigger World',
    class: PlanetClass::Station,
    abilities: [
        new PlanetAbility(
            PlanetAbilityType::DoubleNextPlanetNoCombat,
            params: []
        ),
    ],
);
```

When the player claims `Trigger World`, the engine will look ahead, pull the next planet into the pot, and award it immediately (skipping combat) if one exists.

---

### Pot utilities for UI

The engine exposes helpers to summarize the current pot for display:

```
$engine->potTotalVictoryPoints($state); // int total VP currently in the pot
$engine->potSummary($state);            // ['planets' => [...], 'total_vp' => int, 'count' => int]
```

You can also introspect legal plays per player (currently just “what you have in hand”) via:
```
$engine->legalCardsForPlayer($state, $playerId); // int[]
```

---

### End reasons

`GameState::endReason` (enum `GameEndReason`) gives a precise termination cause, such as:
- `Normal` — all hands empty, all planets awarded
- `ShipsExhaustedPlanetsRemaining` — players ran out of cards while unclaimed planets remain
- `PlayerOutOfCardsEarly` — defensive guard if a player runs out mid‑flow

This helps UIs show accurate end‑of‑game messages.

---

### Development

Run tests (Pest):
```
composer install
./vendor/bin/pest
```

The engine code lives under `src/`:
- `GameEngine` — state transitions (start game, play card, resolve battle)
- `GameState`  — data model + serialization helpers + scoring
- `GameConfig` — setup helpers (player count, deck, fleets)
- `Planet`     — planet model (id, VP, optional name/abilities)

Versioning:
- Current tag: `v.0.1.0`
- SemVer will be followed once the API stabilizes; until then, minor versions may include breaking changes.

MIT licensed. Contributions welcome.
