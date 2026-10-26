# omnilab/col

The Catalogue of Life, through ChecklistBank, for
[omnisense/omnilab](https://github.com/glitchr-omnisense/omnilab): a taxon or a synonym by its COL id,
by the GBIF key that names it, or by its name - its authority, rank, status, classification,
group, vernacular names, distribution, place of publication, the scrutinizer who checked it and
the source record it comes from; its GBIF key and the iNaturalist, WoRMS and ITIS records COL
names for it as cross-references; its parent, accepted name and synonyms as related things. A
search of names. No key; one call a taxon.

```php
$col = (new ColSourceFactory($http))->create(['mailto' => 'support@glitchr.io']);
$lion = $col->entity('4CGXP');                    // or 'col:4CGXP', 'gbif:4CGXP', its page, 'Panthera leo'
$lion->identifiers()->keys();                     // col:4CGXP, gbif:4CGXP, inaturalist:41964, itis:183803
$lion->value('scrutinizer');                      // W. Christopher Wozencraft,Alfred L. Gardner
$col->entity('col:CFSCR')->related('accepted')->keys();   // col:4CGXP: Felis leo is a synonym

$col->search(new Query('Panthera leo', limit: 5));        // 356 names (COL26.9 XR)
```

```yaml
omnilab:
    sources:
        col:
            factory: col
            options:
                mailto: 'support@glitchr.io'   # in the User-Agent
                dataset: 3LXR                  # the latest eXtended Release; 3LR the Base Release; 315557 the one GBIF runs
                language: eng                  # the vernacular names put first
```

## The Catalogue of Life and GBIF

Since 23 June 2026 GBIF's taxonomy is the Catalogue of Life eXtended Release, and **a GBIF key is
the COL id of the same taxon** (`4CGXP` is the lion in both). So:

- a COL entity carries `gbif:<its id>` as an identifier, and `omnilab/col` reads a GBIF key of the
  new form (`gbif:4CGXP`); with `omnilab/gbif` configured, the `Collector` goes from one to the
  other, and the `Merger` takes them for one thing;
- a GBIF **legacy number** (`5219404`, the Backbone frozen since 2023) is not a COL id: refused
  before any call; and a COL id of digits alone (`322`, an order) gives no `gbif:` identifier, the
  core reading digits as a legacy number;
- GBIF runs one release (ChecklistBank 315557, COL26.6 XR of 2026-06-19, on 2026-10-15), COL
  publishes one a month (3LXR was COL26.9 XR of 2026-09-25): a taxon added since is in COL, not
  yet in GBIF, which then answers null. `dataset: 315557` reads the release GBIF runs.

## Terms of use

The Catalogue of Life is published under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/);
each release is to be cited with its DOI - for COL26.9 XR: "Bánki, O., Roskov, Y., Döring, M., et
al. (2026). Catalogue of Life (2026-09-25 XR). Catalogue of Life Foundation, Amsterdam,
Netherlands. https://doi.org/10.48580/dgz9s" - and its source datasets credited (`source_record`
names the record a taxon was taken from). ChecklistBank's API needs no key to read
([catalogueoflife.org/about/citing](https://www.catalogueoflife.org/about/citing),
[api.checklistbank.org](https://api.checklistbank.org/)).

See [docs/](docs/index.md) for what is read, how it maps, and what COL does not give here.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by the Catalogue of Life Foundation and ChecklistBank. It reaches their service on behalf of whoever runs it, and it does not
redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT licence
covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with the terms above, for
obtaining whatever they require (an account, a key, a licence or subscription, an attribution or
a citation, a rate limit to respect), and for any use made of what it returns. The authors
publish it free of charge, make no commercial use of it, and accept no liability for how it is
used (see [LICENSE](LICENSE)).

License: MIT.
