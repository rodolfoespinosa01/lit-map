# Questionnaire question-key mapping

Content source: the team printout transcription supplied in this conversation. No PDF is required or retained. Required flags and some display logic remain unconfirmed.

| Key | Transcribed question | Stored type |
| --- | --- | --- |
| `q01` | Are you a resident of Newark, New Jersey? | choice |
| `q02` | If not, please specify the city/town and state where you currently reside: | text |
| `q03` | If you live in New Jersey, what county do you currently reside in? | text |
| `q04` | How old are you? | age |
| `q05` | How do you identify your gender? | gender |
| `q06` | What is your birth order among your siblings? | choice |
| `q07` | Which languages do you speak or understand, and when did you start learning each one? | languages |
| `q08` | How often do you use Language 1 in the following settings? | frequency |
| `q09` | Do you consider yourself a heritage speaker? | choice |
| `q10` | Rate proficiency in Language 1. | proficiency |
| `q11` | Rate proficiency in Language 2. | proficiency |
| `q12` | Rate proficiency in Language 3. | proficiency |
| `q13` | Do you feel Language 2 is supported in the following settings? | support |
| `q14` | Do you feel Language 3 is supported in the following settings? | support |
| `q15` | Have you ever translated or interpreted for family/community members, e.g. school, healthcare, housing, legal, government? | choice |
| `q16` | How often do you translate or interpret for your family, friends, or community? | choice |
| `q17` | In which settings do you most often translate or interpret? | multiple |
| `q18` | About how old were you when you first started translating or interpreting for family/community members? | age |
| `q19` | When translating/interpreting for your community, do you use tools? | multiple |
| `q20` | How do you feel about being a language broker—translating and interpreting for family, friends, or community? | reflection |
| `q21` | Are language services provided by public institutions in NJ sufficient for your community’s needs? | choice |
| `q22` | Do you support policies requiring NJ government agencies to provide services in languages other than English? | slider |
| `q23` | When accessing language services, have you experienced barriers? | choice |
| `q24` | What barriers have you experienced? | multiple |
| `q25` | Did you ever participate in bilingual education or a dual-language or immersion program studying academic subjects in English plus another language? | choice |
| `q26` | If so, for how many years? | years |
| `q27` | At what level did you participate? | multiple |
| `q28` | Interested in sharing your bilingual/multilingual experience through a recorded interview for this project? | choice |
| `q29` | If Yes, include your email. | email |
| `q30` | To enter your name in the raffle, include full name and email. | contact |

The complete answer options and matrix descriptions are retained in `web/modules/custom/lit_contribution/data/questionnaire.v2.json`. The input schema is `questionnaire-v2.schema.json`. Runtime validation also checks geography, aliases, timestamps and consent/18+ eligibility. See `questionnaire-v2.md` for assumptions, privacy and operations.
