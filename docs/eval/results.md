# Evaluation results

Run date: 2026-11-12 (UTC)

- Library size: 130 games
- Held-out games (10+ hours, matched, tagged): 43

How to read these tables: a ranker only means something relative to the baselines on the same data. See [method.md](method.md).

## Leave-one-out

| ranker      | trials | mean pool | HR@5  | HR@10 | P@5   | P@10  | NDCG@10 | MRR   | mean rank |
| ----------- | ------ | --------- | ----- | ----- | ----- | ----- | ------- | ----- | --------- |
| recommender | 43     | 45.000    | 0.395 | 0.581 | 0.079 | 0.058 | 0.392   | 0.357 | 12.279    |
| random      | 43     | 45.000    | 0.116 | 0.163 | 0.023 | 0.016 | 0.067   | 0.070 | 26.070    |
| tag-overlap | 43     | 45.000    | 0.140 | 0.256 | 0.028 | 0.026 | 0.109   | 0.099 | 19.860    |

## Time-split backtest

Time-split backtest: needs 89 more days of snapshots (collection started 2026-11-11).

## Recommender rank per held-out game

| game                                               | rank | pool |
| -------------------------------------------------- | ---- | ---- |
| Counter-Strike 2                                   | 1    | 45   |
| BioShock Infinite                                  | 15   | 45   |
| Bully: Scholarship Edition                         | 24   | 45   |
| The Witcher 2: Assassins of Kings Enhanced Edition | 7    | 45   |
| Fallout: New Vegas                                 | 10   | 45   |
| Dragon Age: Origins - Ultimate Edition             | 2    | 45   |
| The Elder Scrolls V: Skyrim                        | 6    | 45   |
| Dishonored                                         | 11   | 45   |
| PAYDAY 2                                           | 10   | 45   |
| HITMAN™                                            | 10   | 45   |
| Rocket League                                      | 1    | 45   |
| Grand Theft Auto V Legacy                          | 24   | 45   |
| Metro 2033 Redux                                   | 7    | 45   |
| Divinity: Original Sin Enhanced Edition            | 25   | 45   |
| Fallout 4                                          | 6    | 45   |
| Kingdom Come: Deliverance                          | 1    | 45   |
| Dishonored 2                                       | 6    | 45   |
| BioShock Remastered                                | 1    | 45   |
| Resident Evil 7 Biohazard                          | 24   | 45   |
| Divinity: Original Sin 2                           | 14   | 45   |
| Prey                                               | 4    | 45   |
| Football Manager 2017                              | 39   | 45   |
| The Elder Scrolls V: Skyrim Special Edition        | 1    | 45   |
| PUBG: BATTLEGROUNDS                                | 1    | 45   |
| Assassin's Creed Origins                           | 1    | 45   |
| Wolfenstein II: The New Colossus                   | 4    | 45   |
| Football Manager 2018                              | 39   | 45   |
| DOOM Eternal                                       | 15   | 45   |
| Football Manager 2019                              | 44   | 45   |
| Baldur's Gate 3                                    | 22   | 45   |
| Cyberpunk 2077                                     | 1    | 45   |
| Fallout 76                                         | 5    | 45   |
| Crusader Kings III                                 | 16   | 45   |
| Halo Infinite                                      | 15   | 45   |
| ELDEN RING                                         | 14   | 45   |
| Starfield                                          | 1    | 45   |
| Kingdom Come: Deliverance II                       | 5    | 45   |
| Clair Obscur: Expedition 33                        | 25   | 45   |
| Football Manager 2023                              | 41   | 45   |
| The Elder Scrolls IV: Oblivion Remastered          | 1    | 45   |
| Red Dead Redemption                                | 1    | 45   |
| Indiana Jones and the Great Circle                 | 1    | 45   |
| Battlefield™ 6                                     | 27   | 45   |
