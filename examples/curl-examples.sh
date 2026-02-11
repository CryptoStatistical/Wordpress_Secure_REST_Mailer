#!/usr/bin/env bash
set -euo pipefail

# =============================================================================
# curl-examples.sh — Esempi cURL per il plugin WordPress "My REST Mailer"
# =============================================================================
#
# Questo script contiene diversi esempi di chiamate cURL verso l'endpoint
# REST del plugin My REST Mailer (POST /wp-json/custom/v1/send-email).
#
# Il plugin utilizza una doppia autenticazione:
#   1. WordPress Application Passwords (Basic Auth)
#   2. API Key personalizzata (header X-API-Key)
#
# Prima di eseguire, assicurati di:
#   - Avere un utente WordPress con il permesso "edit_posts"
#   - Aver generato una Application Password (Utenti > Profilo > Application Passwords)
#   - Aver configurato la API Key nella pagina Impostazioni > REST Mailer
#
# Utilizzo:
#   chmod +x curl-examples.sh
#   ./curl-examples.sh
#
# Oppure esegui un singolo esempio copiando e incollando il comando nel terminale.
# =============================================================================

# ─────────────────────────────────────────────────────────────────────────────
# VARIABILI CONFIGURABILI
# Modifica questi valori con le tue credenziali prima di eseguire gli esempi.
# ─────────────────────────────────────────────────────────────────────────────

# URL base del sito WordPress (senza slash finale)
WP_URL="https://example.com"

# Nome utente WordPress (deve avere il permesso edit_posts)
WP_USER="admin"

# Application Password generata dal pannello WordPress
# (Utenti > Profilo > Application Passwords)
# Nota: gli spazi nell'Application Password vengono rimossi automaticamente da cURL
WP_APP_PASS="XXXX XXXX XXXX XXXX XXXX XXXX"

# API Key configurata nella pagina Impostazioni > REST Mailer
API_KEY="la_tua_api_key_segreta_qui"

# Endpoint completo dell'API
ENDPOINT="${WP_URL}/wp-json/custom/v1/send-email"


# =============================================================================
# 1. TEST BASE — Solo campi obbligatori
# =============================================================================
# Invia una email con i soli campi richiesti: to, subject, message.
# I campi opzionali (from, sender_name, reply_to) verranno presi dalle
# impostazioni predefinite del plugin, se configurate.
# Risposta attesa: HTTP 200 con {"status":"success","message":"Email sent successfully to ..."}

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  1. TEST BASE — Solo campi obbligatori"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

curl -s -X POST "${ENDPOINT}" \
  -u "${WP_USER}:${WP_APP_PASS}" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: ${API_KEY}" \
  -d '{
    "to": "destinatario@example.com",
    "subject": "Test Base - My REST Mailer",
    "message": "<h2>Ciao!</h2><p>Questa è una email di test inviata con i soli campi obbligatori.</p>"
  }'

echo ""
echo ""


# =============================================================================
# 2. TEST COMPLETO — Tutti i campi disponibili
# =============================================================================
# Invia una email specificando tutti i campi, sia obbligatori che opzionali.
# I campi opzionali sovrascrivono i valori predefiniti configurati nel plugin:
#   - "from": indirizzo email del mittente
#   - "sender_name": nome visualizzato del mittente
#   - "reply_to": indirizzo per le risposte
# Risposta attesa: HTTP 200 con {"status":"success","message":"Email sent successfully to ..."}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  2. TEST COMPLETO — Tutti i campi disponibili"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

curl -s -X POST "${ENDPOINT}" \
  -u "${WP_USER}:${WP_APP_PASS}" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: ${API_KEY}" \
  -d '{
    "to": "destinatario@example.com",
    "subject": "Test Completo - My REST Mailer",
    "message": "<h1>Email Completa</h1><p>Questa email include <strong>tutti</strong> i campi disponibili.</p><ul><li>Mittente personalizzato</li><li>Nome mittente personalizzato</li><li>Reply-To personalizzato</li></ul>",
    "from": "mittente@example.com",
    "sender_name": "Il Mio Server",
    "reply_to": "risposte@example.com"
  }'

echo ""
echo ""


# =============================================================================
# 3. TEST DESTINATARI MULTIPLI — Lista separata da virgole
# =============================================================================
# Il campo "to" accetta una stringa con indirizzi email separati da virgole.
# Il plugin li analizza, li valida singolarmente e invia l'email a tutti
# i destinatari validi. Gli indirizzi duplicati vengono rimossi automaticamente.
# Risposta attesa: HTTP 200 con la lista di tutti i destinatari nel messaggio.

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  3. TEST DESTINATARI MULTIPLI — Lista separata da virgole"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

curl -s -X POST "${ENDPOINT}" \
  -u "${WP_USER}:${WP_APP_PASS}" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: ${API_KEY}" \
  -d '{
    "to": "primo@example.com, secondo@example.com, terzo@example.com",
    "subject": "Test Destinatari Multipli",
    "message": "<p>Questa email è stata inviata a <strong>tre destinatari</strong> contemporaneamente.</p>"
  }'

echo ""
echo ""


# =============================================================================
# 4. TEST ERRORE AUTENTICAZIONE — Credenziali WordPress errate
# =============================================================================
# Simula un tentativo di accesso con credenziali WordPress sbagliate.
# Il primo livello di autenticazione (Basic Auth) fallisce.
# Risposta attesa: HTTP 403 con codice errore "rest_forbidden" e messaggio
# "Authentication failed. Valid WordPress credentials with edit_posts
# capability are required."

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  4. TEST ERRORE AUTENTICAZIONE — Credenziali errate"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

curl -s -X POST "${ENDPOINT}" \
  -u "utente_sbagliato:password_sbagliata" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: ${API_KEY}" \
  -d '{
    "to": "destinatario@example.com",
    "subject": "Questo non arriverà mai",
    "message": "<p>Test con credenziali sbagliate.</p>"
  }'

echo ""
echo ""


# =============================================================================
# 5. TEST ERRORE API KEY — Chiave API errata
# =============================================================================
# Simula un tentativo con credenziali WordPress corrette ma API Key sbagliata.
# Il primo livello di autenticazione (Basic Auth) ha successo, ma il secondo
# livello (X-API-Key) fallisce.
# Risposta attesa: HTTP 403 con codice errore "rest_invalid_api_key" e
# messaggio "Invalid API Key."
# Nota: se l'header X-API-Key viene omesso del tutto, la risposta sarà
# HTTP 401 con "Missing X-API-Key header."

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  5. TEST ERRORE API KEY — Chiave API errata"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

curl -s -X POST "${ENDPOINT}" \
  -u "${WP_USER}:${WP_APP_PASS}" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: chiave_api_completamente_sbagliata" \
  -d '{
    "to": "destinatario@example.com",
    "subject": "Questo non arriverà mai",
    "message": "<p>Test con API Key errata.</p>"
  }'

echo ""
echo ""


# =============================================================================
# 6. TEST ERRORE VALIDAZIONE — Campo obbligatorio mancante
# =============================================================================
# Invia una richiesta senza il campo "subject" (obbligatorio).
# Il plugin restituisce un errore di validazione perché "subject" è definito
# come required nella registrazione dell'endpoint REST.
# Risposta attesa: HTTP 400 con dettagli sull'errore di validazione.
# Lo stesso accade se manca "to" o "message".

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  6. TEST ERRORE VALIDAZIONE — Campo obbligatorio mancante (subject)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

curl -s -X POST "${ENDPOINT}" \
  -u "${WP_USER}:${WP_APP_PASS}" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: ${API_KEY}" \
  -d '{
    "to": "destinatario@example.com",
    "message": "<p>Manca il campo subject!</p>"
  }'

echo ""
echo ""


# =============================================================================
# 7. TEST CON VERBOSE/DEBUG — Flag -v per il debug completo
# =============================================================================
# Usa il flag -v (verbose) di cURL per mostrare l'intera comunicazione HTTP:
#   - Risoluzione DNS e connessione TLS
#   - Headers della richiesta inviata (inclusi quelli di autenticazione)
#   - Headers della risposta ricevuta dal server
#   - Corpo della risposta JSON
# Utile per diagnosticare problemi di connessione, certificati SSL,
# redirect, o errori lato server.
# L'opzione -w stampa anche il codice HTTP alla fine per una verifica rapida.

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  7. TEST CON VERBOSE/DEBUG — Output completo della comunicazione"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

curl -v -X POST "${ENDPOINT}" \
  -u "${WP_USER}:${WP_APP_PASS}" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: ${API_KEY}" \
  -w "\n\n--- Codice HTTP risposta: %{http_code} ---\n" \
  -d '{
    "to": "destinatario@example.com",
    "subject": "Test Verbose/Debug",
    "message": "<p>Email inviata con output di debug completo.</p>"
  }'

echo ""
echo ""


# =============================================================================
# 8. TEST CON JQ — Pretty-print della risposta JSON
# =============================================================================
# Usa il programma "jq" per formattare e colorare la risposta JSON del server.
# Requisito: jq deve essere installato (sudo apt install jq / brew install jq).
# Il flag -s di cURL sopprime la barra di progresso, e l'output viene passato
# direttamente a jq per la formattazione.
# Utile per leggere facilmente risposte complesse o errori di validazione.

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  8. TEST CON JQ — Risposta JSON formattata"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Verifica che jq sia installato prima di procedere
if command -v jq &> /dev/null; then
  curl -s -X POST "${ENDPOINT}" \
    -u "${WP_USER}:${WP_APP_PASS}" \
    -H "Content-Type: application/json" \
    -H "X-API-Key: ${API_KEY}" \
    -d '{
      "to": "destinatario@example.com",
      "subject": "Test con jq",
      "message": "<h2>Pretty Print</h2><p>La risposta verrà formattata con <code>jq</code>.</p>",
      "from": "mittente@example.com",
      "sender_name": "Server di Test",
      "reply_to": "noreply@example.com"
    }' | jq '.'
else
  echo "ATTENZIONE: jq non è installato. Installalo con:"
  echo "  Ubuntu/Debian: sudo apt install jq"
  echo "  macOS:         brew install jq"
  echo ""
  echo "Esecuzione senza jq (output JSON non formattato):"
  echo ""
  curl -s -X POST "${ENDPOINT}" \
    -u "${WP_USER}:${WP_APP_PASS}" \
    -H "Content-Type: application/json" \
    -H "X-API-Key: ${API_KEY}" \
    -d '{
      "to": "destinatario@example.com",
      "subject": "Test con jq",
      "message": "<h2>Pretty Print</h2><p>La risposta verrà formattata con <code>jq</code>.</p>",
      "from": "mittente@example.com",
      "sender_name": "Server di Test",
      "reply_to": "noreply@example.com"
    }'
fi

echo ""
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Tutti gli esempi completati."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
