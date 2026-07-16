# ATK Sources

Reviewed: June 11, 2026.

## Official Online Sources

- [ATK guides, manuals, and regulations](https://www.atk-ks.org/udhezues-manuale-dhe-rregullore/)
- [ATK C# POS sample](https://github.com/fiskalizimi/pos-csharp)
- [Current C# protobuf schema](https://github.com/fiskalizimi/pos-csharp/blob/main/models.proto)
- [C# cancel/return documentation correction](https://github.com/fiskalizimi/pos-csharp/commit/a4060ef088387d73ff6b0f09a28dcddca6fc9ea8)
- [ATK PHP POS sample](https://github.com/fiskalizimi/pos-php)
- [ATK Go POS sample](https://github.com/fiskalizimi/pos-golang)
- [ATK test Swagger](https://fiskalizimi-test.atk-ks.org/swagger/index.html)

## Reviewed Documents

The PDFs are intentionally not committed. The hashes identify the exact local
copies used for the June 11, 2026 review.

| Document filename | SHA-256 |
| --- | --- |
| `UDHEZIM_ADMINISTRATIV_MF_NR._01_2026.pdf` | `70792E81833BDA72285EE088582E7E9798C0334058AFB785A105C4AE68A52978` |
| `Kushtet-dhe-Procedurat-per-Aplikimin-Certifikimin-dhe-Mirembajtjen-e-Softuereve-Elektronike-Fiskale-SEF.pdf` | `AF6950D32B4202F78D2C7C18DE5A8766A285648D707C1B347C789291B67E0B77` |
| `Kerkesat-Specifike-Teknike-dhe-Funksionale-per-Pajisjet-Elektronike-FiskaleSistemet-FiskaleSoftueret-Elektronike-Fiskale.pdf` | `69AC172E1903DFCA9DAC4F10B4773F96458BCB6896DA3B88C49D441803340962` |

Always download current official copies and compare their revision and hash
before making implementation or certification decisions.

## Revision Drift

Checked July 16, 2026.

The copy of `Kerkesat-Specifike-Teknike...` currently published by ATK hashes
`66518E9908086B959BE98A42AD4A9E4CCA7560E34E8A13D7F054224C63C0BC37`, which does
**not** match the `69AC172E...` copy reviewed on June 11, 2026. ATK has
republished the document; the June 11 review is against a superseded revision
and should be redone before certification.

| Document | URL | SHA-256 |
| --- | --- | --- |
| Kerkesat Specifike Teknike (SQ, live) | [atk-ks.org](https://www.atk-ks.org/wp-content/uploads/2018/12/Kerkesat-Specifike-Teknike-Dhe-Funksionale-per-Pajisjet-Elektronike-Fiskale-Sistemet-Fiskale-Softueret-Elektronike-Fiskale.pdf) | `66518E9908086B959BE98A42AD4A9E4CCA7560E34E8A13D7F054224C63C0BC37` |
| Kerkesat Specifike Teknike (EN, live) | [atk-ks.org](https://www.atk-ks.org/wp-content/uploads/2018/12/ENG-Kerkesat-Specifike-Teknike-Dhe-Funksionale-per-Pajisjet-Elektronike-Fiskale-Sistemet-Fiskale-Softueret-Elektronike-Fiskale.pdf) | `B116A4EAFD16A8AB1A455BAD270A2AD7CEFFF3ED3E87E1210CB32FEBEDC53A2A` |

The two language editions do not agree on the NUIKF, and their article
numbering differs. See the open questions in `requirements.md`. The Albanian
edition is authoritative; the English edition is useful for locating a rule but
must not be cited as the requirement.
