# Literary fragments

Short original-language translation inputs, checked on 2026-10-26. The 16 literary excerpts below were checked in full against the cited original-language transcriptions or scan; the Latin file is explicitly a placeholder. Filenames are retained for continuity. These are varied short inputs, not a gold-standard translation benchmark. Use the [authored quickstart inputs](../quickstart/) for routine examples.

“Checked” means the complete current payload matches the cited passage after the formatting changes described below. It does not mean the whole work or source transcription was reviewed. Only the Icelandic excerpt was checked directly against a page scan. Print editions are recorded when the source identifies them; “unspecified” means that detail remains unestablished. No literary attribution remains wholly unchecked, but the unspecified print editions and transcription accuracy beyond the selected passages remain unresolved.

These files are third-party material, not a blanket MIT-licensed corpus. The excerpts reproduce original-language literary text, not modern translations, introductions or editorial commentary. An old work’s date alone does not establish rights in a particular transcription or edition. Consult each linked source for provenance and applicable reuse terms; this index makes no corpus-wide permission claim.

| File | Language | Author / work | Exact extent and source edition | Verification status | Source |
|---|---|---|---|---|---|
| `bg_vazov_pod_igoto_ch1.txt` | bg | Ivan Vazov, *Под игото* | Part I, chapter I, first paragraph; print edition unspecified | Complete excerpt checked | [Source](https://bg.wikisource.org/w/index.php?oldid=14703) |
| `cs_hasek_svejk_ch1.txt` | cs | Jaroslav Hašek, *Osudy dobrého vojáka Švejka za světové války* | Chapter 1, first seven paragraphs; source identifies Prague: Československý spisovatel, 1983, 32nd edition | Complete excerpt checked | [Source](https://cs.wikisource.org/w/index.php?oldid=185857) |
| `de_grimm_rotkaeppchen.txt` | de | Brothers Grimm, *Rothkäppchen* | First paragraph; *Kinder- und Haus-Märchen*, vol. I, Göttingen: Dieterich, 1857, 7th edition, p. 140 | Complete excerpt checked | [Source](https://de.wikisource.org/w/index.php?oldid=1644437) |
| `en_austen_pride_prejudice_ch1.txt` | en | Jane Austen, *Pride and Prejudice* | Chapter 1, first two paragraphs; London: George Allen, 1894, Gutenberg #1342 | Complete excerpt checked | [Source](https://www.gutenberg.org/files/1342/1342-h/1342-h.htm#CHAPTER_I) |
| `el_john1.txt` | el (Ancient Greek) | *Gospel of John* | John 1:1–5; Wikisource links its source to *The New Testament in the Original Greek* (1894) | Complete excerpt checked | [Source](https://el.wikisource.org/w/index.php?oldid=148123) |
| `es_cervantes_don_quijote_ch1.txt` | es | Miguel de Cervantes, *Don Quijote* | Chapter 1, opening through “su hacienda”; Gutenberg #2000, print edition unspecified | Complete excerpt checked | [Source](https://www.gutenberg.org/cache/epub/2000/pg2000-images.html) |
| `et_kalevipoeg_alustus.txt` | et | F. R. Kreutzwald, *Kalevipoeg* | “Soovituseks”, first 16 lines; source index identifies 1961 edition | Complete excerpt checked | [Source](https://et.wikisource.org/w/index.php?oldid=57766) |
| `fi_seitseman_veljesta_ch1.txt` | fi | Aleksis Kivi, *Seitsemän veljestä* | Chapter 1, first three paragraphs; print edition unspecified | Complete excerpt checked | [Source](https://fi.wikisource.org/w/index.php?oldid=110463) |
| `fr_perrault_chaperon_rouge.txt` | fr | Charles Perrault, *Le petit chaperon rouge* | First paragraph; *Histoires ou Contes du temps passé*, Paris: Claude Barbin, 1697, pp. 47–48 | Complete excerpt checked | [Source](https://fr.wikisource.org/w/index.php?oldid=12442005) |
| `hu_aranyember_reszlet.txt` | hu | Mór Jókai, *Az arany ember* | “A Vaskapu”, opening two paragraphs; MEK-00688 identifies Szentendre: Interpopulart, 1994 | Complete excerpt checked | [Source](https://mek.oszk.hu/00600/00688/html/01.htm) |
| `is_prose_edda_snorra.txt` | is (Old Icelandic) | Snorri Sturluson, *Edda Snorra Sturlusonar* | Formáli, first sentence; vol. I, Copenhagen: Sumptibus Legati Arnamagnæani, 1848, p. 1 | Checked against page scan | [Source](https://archive.org/details/eddasnorrasturlu01hafnuoft/page/n17/mode/1up) |
| `it_dante_inferno_canto1.txt` | it | Dante Alighieri, *Divina Commedia: Inferno* | Canto I, lines 1–9; print edition unspecified | Complete excerpt checked | [Source](https://it.wikisource.org/w/index.php?oldid=3857646) |
| `la_lorem_ipsum.txt` | la (placeholder) | Anonymous placeholder tradition, *Lorem ipsum* | Conventional pseudo-Latin placeholder; not an authenticated excerpt from Cicero | Explicit placeholder; no canonical attribution | — |
| `pl_mickiewicz_pantadeusz_tom1.txt` | pl | Adam Mickiewicz, *Pan Tadeusz* | Book I, first 13 lines; Paris: Alexander Jełowicki, 1834, pp. 5–6 | Complete excerpt checked | [Source](https://pl.wikisource.org/w/index.php?oldid=3275843) |
| `pt_camoes_lusadas_canto1.txt` | pt | Luís de Camões, *Os Lusíadas* | Canto I, first two stanzas; Gutenberg #3333, print edition unspecified | Complete excerpt checked | [Source](https://www.gutenberg.org/cache/epub/3333/pg3333-images.html) |
| `ru_gogol_dead_souls_ch1.txt` | ru | Nikolai Gogol, *Мёртвые души* | Volume I, chapter I, first paragraph; print edition unspecified | Complete excerpt checked | [Source](https://ru.wikisource.org/w/index.php?oldid=5640514) |
| `uk_kotliarevsky_eneida_ch1.txt` | uk (historical spelling) | Ivan Kotliarevsky, *Енеида на Малороссійскій языкъ перелицїованная* | Part I, first two stanzas; Saint Petersburg: Ivan Glazunov, 1808, second edition | Complete excerpt checked | [Source](https://uk.wikisource.org/w/index.php?oldid=211981) |

## Extraction and normalization

Common formatting changes: UTF-8 text with LF line endings and a final newline; prose paragraphs separated by blank lines; verse kept one line per verse. Source page labels, verse/stanza counters, footnote callouts, headings and navigation are omitted. Nonbreaking or indentation spaces become ordinary spaces; print line wrapping is removed from prose. Historical spelling and textual punctuation are retained. No padding, invented continuation or modern-language paraphrase is added.

- English: initial drop-cap styling is rendered as `IT`; illustration captions are outside the excerpt.
- Greek: verse numbers omitted; one verse per line. Source punctuation and capitalization retained.
- Estonian: first 16 lines of “Soovituseks”, without indentation or line counters. Historical spellings retained.
- Icelandic: initial small capitals rendered as `Almáttigr`; spaces before commas and the superscript footnote marker omitted. The opening `[` before `ok fjölgaðist` is an editorial reading mark printed in the 1848 edition and is deliberately retained; no closing bracket is supplied.
- Italian: verse counters omitted; source apostrophe spacing retained, including `v’ ho`.
- Polish: footnote callout after “Bramie!” omitted; 1834 spelling, including `é`, retained.
- Ukrainian: initial drop-cap spacing joined; inter-stanza ornament omitted; nonbreaking space before the dash normalized. Historical spelling retained.
- Latin: conventional pseudo-Latin placeholder, not an authenticated Cicero quotation.
