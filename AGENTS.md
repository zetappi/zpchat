# AGENTS.md - Core System Instructions & Agent Skills

Questo documento definisce i vincoli operativi, gli standard di codifica e le competenze obbligatorie per l'Agente AI in questo progetto. Non deviare da questi standard senza esplicita autorizzazione.

## 🧠 Required Skill Sets & Profiles

L'Agente deve operare utilizzando i seguenti framework mentali e pattern estratti dalle library specificate:



2.  **Frontend Design Expert** (`anthropics/skills/frontend-design`):
    * Focus su Accessibilità (A11Y) e User Experience.
    * Design system-first approach (uso di variabili CSS/Tailwind, componentizzazione).
    * Gestione degli stati UI (Loading, Error, Empty states).

3.  **Modern JavaScript Patterns** (`wshobson/agents/modern-javascript-patterns`):
    * Uso di ES6+ (Optional chaining, Destructuring, Async/Await).
    * Pattern Funzionali sopra quelli Imperativi.
    * Modularità e gestione del modulo ESM.

4.  **SQL Optimization** (`github/awesome-copilot/sql-optimization`):
    * Indicizzazione intelligente delle tabelle.
    * Analisi dei piani di esecuzione.
    * Prevenzione attiva di query ridondanti e ottimizzazione delle JOIN.

## 🛠️ Project Specific Logic: SSO & Security

Oltre agli skill-set sopra citati, l'agente deve applicare rigorosamente i seguenti pattern per il modulo di autenticazione:

- **SSO Pattern:** Implementare esclusivamente tramite **JWT (JSON Web Tokens)** firmati con scadenza breve (max 60s) per il trasferimento di sessione tra Forum e App Esterna.
- **Security:** È proibito il passaggio di `session_id` o parametri sensibili in chiaro nell'URL (URL Rewriting).
- **Communication:** Utilizzare secret condivisi archiviati nel file `.env` per la validazione cross-app.

## 🚀 Workflow Requirements

1.  **Pre-computation:** Prima di scrivere codice, l'agente deve analizzare se la soluzione rispetta i pattern di *Modern JS* e *Laravel Specialist*.
2.  **SQL Check:** Ogni nuova migrazione o query Eloquent deve essere validata secondo i principi di *SQL Optimization*.
3.  **Frontend Consistency:** Ogni modifica alla UI deve seguire i principi di *Frontend Design* per garantire consistenza visiva.

---
*Nota: La violazione di questi skill-set comporterà il rigetto della Pull Request.*