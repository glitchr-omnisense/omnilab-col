# omnilab/col

The Catalogue of Life through ChecklistBank's API (`https://api.checklistbank.org/`) for
[omnisense/omnilab](https://github.com/glitchr-omnisense/omnilab): a taxon or a synonym by its COL
id, its GBIF key (the same id since June 2026) or its name - classification, vernacular names,
distribution, scrutinizer, source record - with its GBIF, iNaturalist, WoRMS and ITIS ids as
cross-references; a search of names. No key.

## Installation

```sh
composer require omnilab/col
```

```php
use Omnilab\Col\ColSourceFactory;

$col = (new ColSourceFactory($httpClient))->create([
    'mailto' => 'support@glitchr.io',  // optional, in the User-Agent
    'dataset' => '3LXR',               // optional
]);
```

With the Symfony bundle, the factory is registered as `col` (see the core's docs).

## Credentials

None: ChecklistBank's API needs no key to read.

## Options

The factory's options, with the defaults it applies (`ColSourceFactory`):

| Option | Type | Default | Required | Effect |
|---|---|---|---|---|
| `dataset` | `string` | `3LXR` | no | the ChecklistBank dataset read: `3LXR` (the latest eXtended Release), `3LR` (the latest Base Release), or a release's number (`315557`: the release GBIF runs) |
| `language` | `?string` | `eng` | no | the vernacular names put first (and whose first is `common_name`), by ISO 639-3 code; null, empty or `all`: COL's order |
| `mailto` | `?string` | null | no | a contact address, sent in the User-Agent |
| `user_agent` | `?string` | null | no | the whole User-Agent, in place of the one made from `mailto` |
| `throttle` | `float` | `0` | no | seconds left between two calls: ChecklistBank publishes no rate |
| `base_uri` | `string` | `https://api.checklistbank.org/` | no | the API the source calls |

## Capabilities

`capabilities()` answers `entity`, `search`, `cross_references`; an operation it does not answer throws `NotSupportedException` before any call.

| Capability | Supported | |
|---|---|---|
| `entity` | yes | a COL id (`4CGXP`, `col:`, its pages), a GBIF key of the new form (`gbif:4CGXP`: the same id; a legacy number refused), or an exact scientific name (the accepted usage first); another scheme refused before any call; one call by id |
| `search` | yes | ChecklistBank's name search, its order, 1,000 a page at most, paged by offset, with a `total`; a domain other than taxonomy answers an empty page |
| `series` | no |  |
| `products` | no |  |
| `occurrences` | no | GBIF holds them (omnilab/gbif) |
| `datasets` | no |  |
| `jobs` | no |  |
| `open` | no |  |
| `structure` | no |  |
| `cross_references` | yes | its GBIF key, and the first id of each base COL names or links to as its source record (iNaturalist, WoRMS, ITIS...) |
| `forecast` | no |  |
| `historical` | no |  |
| `area` | no |  |
| `station` | no |  |
| `cloud_cover` | no |  |

## Which API, which release

ChecklistBank's API, `https://api.checklistbank.org/`, on one dataset: `3LXR` (the latest
eXtended Release, ChecklistBank's alias: COL26.9 XR, dataset 316441, 8,479,599 usages on
2026-10-15) by default; `3LR` for the latest Base Release (COL26.9, 316321: the expert-checked
core the XR extends); any release's number (`315557`: COL26.6 XR, the release GBIF's taxonomy
was built from). A COL id is stable from one release to the next.

- `dataset/{key}/taxon/{id}/info`: a usage - accepted or synonym - with its classification,
  synonyms, vernacular names, distributions, references and place of publication, in one call;
  404 when unknown;
- `dataset/{key}/nameusage/search?q=&type=EXACT`: a name's usages;
- `dataset/{key}/nameusage/search?q=&limit=&offset=`: a search, with its total.

## Calls

| Method | Reads | ChecklistBank |
|---|---|---|
| `entity()` | a COL id: `4CGXP`, `col:4CGXP`, `https://www.catalogueoflife.org/data/taxon/4CGXP`, `https://www.checklistbank.org/dataset/3LXR/taxon/4CGXP` | `taxon/4CGXP/info` |
| | a GBIF key of the new form: `gbif:4CGXP` (a legacy number is refused) | `taxon/4CGXP/info` |
| | an exact scientific name (`Panthera leo`; of several usages the accepted first) | `nameusage/search?type=EXACT`, then `taxon/{id}/info` |
| `search()` | `Query::$text`, ChecklistBank's order, page by offset (1,000 at most), its total; a domain other than taxonomy answers nothing | `nameusage/search?q=&limit=&offset=` |

A bare string of upper-case letters and digits is an id, anything else a name. A synonym is itself
(`synonym`, its accepted taxon related). An identifier of another scheme is refused before any
call.

## What an entity holds

`id` (`col:4CGXP`), `label` (its scientific name), `domain` taxonomy, `kind` (its rank),
`synonyms` (its vernacular names, `language` first, then its scientific synonyms), `url` (its
page on catalogueoflife.org), and:

| Key | ChecklistBank | Value |
|---|---|---|
| `taxon_name`, `taxon_authority`, `scientific_name` | name.scientificName, name.authorship, both | text |
| `taxon_rank`, `taxonomic_status` | name.rank; status: `accepted`, `provisionally accepted`, `synonym`, `ambiguous synonym`, `misapplied` | text |
| `nomenclatural_code`, `nomenclatural_status` | name.code, name.nomStatus | text |
| `accepted_name` | accepted.label, for a synonym | text |
| `kingdom`, `phylum`, `class`, `order`, `family`, `genus` | the classification's | text |
| `lineage` | the classification, from the root | text |
| `group` | COL's informal group (`chordates`) | text |
| `extinct`, `environment` | extinct; environments (`marine`, `terrestrial`...) | boolean, text |
| `published_in` | publishedIn.citation | text |
| `scrutinizer`, `scrutinized` | the expert who checked it, the day | text |
| `source_record` | the link to the record COL took it from (an ITIS or WoRMS page...) | text |
| `common_name` | the first vernacular name in `language` | text |
| `distribution` | the distributions' areas | text |

`identifiers`: `gbif` (the same id, when not digits alone), and one of each of the bases COL names
(`inat:` → `inaturalist`, `worms:` → `worms`, and the others the core has a scheme for) or links
to as the source record (an ITIS or WoRMS page): `itis:183803` for the lion, taken from ITIS. COL
names the ids of the names it merged too - the dolphin's `worms:220224` is WoRMS's misspelling
*Delphinus delphus* -: only the first of each base is kept. `related`: `parent` (the
classification's last rank above, for an accepted taxon), `accepted`, `synonym`.

A search's result holds the usage and its classification: no vernacular names, distribution or
synonyms.

## What COL does not give here

- Not read: the references one by one, the name relations (basionyms), the type material, the
  sectors' and source datasets' records, the estimates, the tree browser; the ids of bases the
  core has no scheme for (TAXREF, Dyntaxa, TaiCOL).
- No occurrence: GBIF holds them (`omnilab/gbif`).

## The Collector

Walked on 2026-10-15:

- `collect('col', '4CGXP', 1, ['gbif', 'inaturalist', 'itis'])`: the lion in COL, then GBIF by
  the same key, iNaturalist by 41964 and ITIS by its TSN 183803 (taken from ITIS's record).
- `collect('col', 'Delphinus delphis', 1, ['worms', 'itis'])`: COL's dolphin into WoRMS 137094 and
  ITIS 180438.
- From GBIF or OBIS: an entity carrying `gbif:4CGXP` reaches COL by the same key.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnilab\Col\ColSourceFactory;
use Omnilab\Model\Scheme;
use Omnilab\Source\Query;
use Symfony\Component\HttpClient\HttpClient;

$col = (new ColSourceFactory(HttpClient::create()))->create(['mailto' => 'you@example.org']);

$lion = $col->entity('gbif:4CGXP');   // GBIF's key is COL's id
echo $lion->label, ' ', $lion->value('taxon_authority'), ' (', $lion->id, '): ', $lion->value('taxonomic_status'), ', ', $lion->value('common_name'), ', scrutinized by ', $lion->value('scrutinizer'), "\n";
echo '  GBIF ', $lion->identifier(Scheme::GBIF), ', iNaturalist ', $lion->identifier(Scheme::INATURALIST), ', ITIS ', $lion->identifier(Scheme::ITIS), ', parent ', $lion->related('parent')->value(Scheme::COL), "\n";

$page = $col->search(new Query('Panthera leo', limit: 3));
printf("%d names for \"Panthera leo\"; the first %d:\n", $page->total, count($page));
foreach ($page as $usage) {
    echo '  ', $usage->id, '  ', $usage->label, '  ', $usage->value('taxonomic_status'), "\n";
}
```

```
Panthera leo (Linnaeus, 1758) (col:4CGXP): accepted, African Lion, scrutinized by W. Christopher Wozencraft,Alfred L. Gardner
  GBIF 4CGXP, iNaturalist 41964, ITIS 183803, parent 6DBT
359 names for "Panthera leo"; the first 3:
  col:4CGXP  Panthera leo  accepted
  col:5K5L8  Panthera leo leo  accepted
  col:7KGW9  Panthera leo melanochaita  accepted
```

(as answered on 2026-10-27 by the latest XR: two calls)

```yaml
# config/packages/omnilab.yaml
omnilab:
    sources:
        col: { factory: col, options: { mailto: '%env(OMNILAB_MAILTO)%', dataset: 3LXR, language: eng } }
```

## Errors

Every call goes through `HttpSource`: a 404 or a 410 (and a 204) is null - an empty page for a list -; a 429 is a `RateLimitedException` (`retryAfter` from `Retry-After`); a 5xx, a time-out or a host that does not answer an `UnavailableException`; any other 4xx a `ProviderException` with its `status` and what the service said; an answer that is not JSON a `ProviderException`, a web page answered for JSON an `UnavailableException`.

Before any call: an identifier of another scheme, or a legacy GBIF number (digits alone)
(`NotSupportedException`). An unknown id is ChecklistBank's 404, null; an unknown name an empty
search, null.

## Verified, and not

Against the real service on 2026-10-15, without a key: the lion by its id, its GBIF key, its name
and its two pages (one call each; its GBIF, iNaturalist and ITIS ids, *Panthera* its parent, *Felis
leo* among its synonyms; the scrutinizers, its distribution); *Felis leo* (CFSCR) a synonym, the lion
accepted; the common dolphin by its name (WoRMS 137094, ITIS 180438, iNaturalist 41526); an unknown
id (404) and name; a search for "Panthera leo" (356 names) in two pages; the lion in the release
GBIF runs (315557). GBIF answered `usageKey=34JWM` with *Delphinus delphis*: COL's id is GBIF's key.
The fixtures in `Tests/Fixtures/` are its answers (`index.json` names each one's address).

Again on 2026-10-27: the lion by its GBIF key (its iNaturalist and ITIS ids, its parent), the
search for "Panthera leo" (359 names; the example above).

Not verified: a 429 or an outage (none met); a taxon added after COL26.6 XR, absent from GBIF.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by the Catalogue of Life or ChecklistBank. The Catalogue of Life is under
[CC BY 4.0](https://creativecommons.org/licenses/by/4.0/); each release is cited with its DOI and
its source datasets credited (`source_record`), as the README's
[Terms of use](https://github.com/glitchr-omnisense/omnilab-col#terms-of-use) says. Whoever runs
the package is responsible for complying with them and for any use made of what it returns: see
the README's [Responsibility](https://github.com/glitchr-omnisense/omnilab-col#responsibility)
section.
