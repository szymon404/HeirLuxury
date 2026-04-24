# Gotchas

Running log of mistakes, near-misses, and surprise landmines — plus the rules that prevent them next time. Review at session start before new work.

---

## Triplicated `$brandMap` across Artisan commands

**Symptom waiting to happen:** adding a new brand (e.g. Balenciaga) requires editing the `$brandMap` array in *three* files. Miss one and that command silently routes products to the wrong brand slug — no error, no test failure, just wrong data in production.

**Where the duplication lives:**
- [app/Console/Commands/ImportLV.php](app/Console/Commands/ImportLV.php) — `$brandMap` property
- [app/Console/Commands/SplitMergedAlbums.php](app/Console/Commands/SplitMergedAlbums.php) — `$brandMap` property (comment even admits "Identical to ImportLV::$brandMap")
- [app/Console/Commands/PickCoverImages.php](app/Console/Commands/PickCoverImages.php) — presumed same pattern

**Rule:** never copy a mapping table into a second class. When the second consumer appears, that's the trigger to extract to `config/brands.php` and read via `config('brands.map')`.

**Follow-up task (separate branch, not in `feature/importer-unisex-support`):**
1. Create `config/brands.php` with the canonical prefix → brand-name map.
2. Replace all three `$brandMap` properties with `config('brands.map')` reads.
3. Add a unit test asserting every brand in `config/brands.php` has at least one matching slug in `config/categories.php` (catches "added brand, forgot taxonomy" drift).

---

## Scraper produces folders with duplicated brand prefix

**Symptom:** Yupoo scraper dumps folders named `Celine Celine women bags` (brand doubled) containing an inner folder of the same name, then products. The `import:lv` command expects `celine-bags-women` flat. Running the importer against raw dump output silently skips every folder as "unrecognized."

**Rule:** scraper output is not importer input. Always run the `scripts/migrate-dump.php` rename-and-flatten step before `php artisan import:lv`.

**Secondary gotcha inside the dump:**
- Some brands have typos in the doubled prefix (`Nike NIke` — capital I). Case-insensitive dedupe in the migrate script handles this; don't tighten to case-sensitive.
- Some folders lack a section (`McQueen McQueen men`). The migrate script defaults missing sections via `DEFAULT_SECTIONS`; add new brand entries there if more sectionless brands appear.
- Some folders use `unisex` as gender. The importer's `parseFolderName()` expands `unisex` into `men` + `women` slugs (option B: duplicate rows per-gender). DB rows carry the binary gender, not `unisex` — the unisex origin is lost after import.

---
