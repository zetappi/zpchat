# Cose da sistemare

## Bug critici

### 1. Icona chat muta scomparsa
La mini icona per mutare la chat è scomparsa dall'interfaccia. Verificare il CSS e il DOM per ripristinarla.

### 2. Link "Globale" appare anche se chat globale disattivata
Il pulsante "Torna a chat globale" appare anche quando la chat globale è disabilitata nelle impostazioni ACP. Deve essere nascosto quando `zpchat_allow_global` è disabilitato.

## Miglioramenti

### 3. Perfezionare il push e il collegamento utente-utente
- Verificare che le notifiche push funzionino correttamente per chat private
- Migliorare l'affidabilità della connessione SSE
- Aggiungere indicatore di stato online/offline per gli utenti

### 4. Stanze chat multiutente (ipotesi per futuro)
Valutare implementazione di stanze chat multiutente (group chat):
- Tabella pivot `zpchat_conversation_participants`
- UI per creare e gestire group chat
- Sistema notifiche per group chat
- Metadati conversazioni (nome, avatar, ultimo messaggio)

## Performance

### 5. SSE implementation
- Verificare che SSE riduca correttamente il carico CPU
- Testare fallback a polling in caso di errori
- Monitorare connessioni persistenti

## UX

### 6. Icona chat nell'avatar
- Verificare che l'icona appaia in tutti gli stili phpBB
- Migliorare posizionamento dell'icona
- Aggiungere tooltip più descrittivi

## Documentazione

### 7. Aggiornare documentazione
- Documentare implementazione SSE
- Aggiungere guida troubleshooting per problemi comuni
- Documentare configurazione ACP completa
