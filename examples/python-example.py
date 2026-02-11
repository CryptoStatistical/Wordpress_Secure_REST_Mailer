#!/usr/bin/env python3
"""
python-example.py — Client Python per il plugin WordPress "My REST Mailer"

Questo script dimostra come inviare email tramite l'endpoint REST del plugin
My REST Mailer (POST /wp-json/custom/v1/send-email).

Il plugin richiede doppia autenticazione:
  1. WordPress Application Passwords (Basic Auth)
  2. API Key personalizzata (header X-API-Key)

Requisiti:
  pip install requests

Configurazione:
  Impostare le variabili d'ambiente oppure modificare le costanti qui sotto:
    export WP_URL="https://example.com"
    export WP_USER="admin"
    export WP_APP_PASS="XXXX XXXX XXXX XXXX XXXX XXXX"
    export MRM_API_KEY="la_tua_api_key_segreta"

Utilizzo:
  python3 python-example.py

Compatibile con cron job:
  */30 * * * * /usr/bin/python3 /path/to/python-example.py >> /var/log/mrm.log 2>&1
"""

import logging
import os
import sys
import time
from typing import Any, Optional

import requests
from requests.exceptions import ConnectionError, HTTPError, Timeout

# =============================================================================
# CONFIGURAZIONE
# =============================================================================
# Le variabili d'ambiente hanno la precedenza sulle costanti.
# Per i cron job, è consigliato usare le variabili d'ambiente o un file .env.

WP_URL: str = os.environ.get("WP_URL", "https://example.com")
WP_USER: str = os.environ.get("WP_USER", "admin")
WP_APP_PASS: str = os.environ.get("WP_APP_PASS", "XXXX XXXX XXXX XXXX XXXX XXXX")
MRM_API_KEY: str = os.environ.get("MRM_API_KEY", "la_tua_api_key_segreta")

# Endpoint completo dell'API REST
API_ENDPOINT: str = f"{WP_URL.rstrip('/')}/wp-json/custom/v1/send-email"

# Timeout per le richieste HTTP (in secondi)
REQUEST_TIMEOUT: int = 30

# Pausa tra invii nel batch (in secondi) per rispettare il rate limiting
BATCH_DELAY: float = 2.0

# =============================================================================
# LOGGING
# =============================================================================
# Configurazione del modulo logging.
# Il formato include timestamp, livello e messaggio per facilitare il debug.
# Nei cron job, l'output viene rediretto su file automaticamente.

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
    handlers=[
        logging.StreamHandler(sys.stdout),
    ],
)

logger: logging.Logger = logging.getLogger("my-rest-mailer")


# =============================================================================
# FUNZIONE PRINCIPALE DI INVIO
# =============================================================================


def send_email(
    to: str,
    subject: str,
    message: str,
    from_email: Optional[str] = None,
    sender_name: Optional[str] = None,
    reply_to: Optional[str] = None,
) -> dict[str, Any]:
    """Invia una email tramite l'endpoint REST di My REST Mailer.

    Effettua una richiesta POST autenticata all'endpoint del plugin WordPress,
    con doppia autenticazione (Basic Auth + API Key).

    Args:
        to: Indirizzo email del destinatario. Accetta anche una stringa con
            indirizzi multipli separati da virgola (es. "a@x.com, b@x.com").
        subject: Oggetto dell'email.
        message: Corpo dell'email. Supporta HTML (es. "<h1>Ciao</h1>").
        from_email: Indirizzo email del mittente. Se omesso, viene usato il
            valore predefinito configurato nel plugin.
        sender_name: Nome visualizzato del mittente. Se omesso, viene usato
            il valore predefinito configurato nel plugin.
        reply_to: Indirizzo Reply-To. Se omesso, viene usato il valore
            predefinito configurato nel plugin.

    Returns:
        Dizionario con la risposta JSON del server. Esempio di successo:
        {"status": "success", "message": "Email sent successfully to ..."}

    Raises:
        ConnectionError: Impossibile connettersi al server WordPress.
        Timeout: Il server non ha risposto entro il tempo limite.
        HTTPError: Il server ha restituito un codice di errore HTTP (4xx/5xx).
        ValueError: Configurazione mancante (URL, credenziali o API Key).
    """
    # Validazione della configurazione
    if not WP_URL or WP_URL == "https://example.com":
        raise ValueError(
            "WP_URL non configurato. Imposta la variabile d'ambiente WP_URL "
            "o modifica la costante nello script."
        )
    if not WP_USER or not WP_APP_PASS:
        raise ValueError(
            "Credenziali WordPress non configurate. Imposta WP_USER e WP_APP_PASS."
        )
    if not MRM_API_KEY or MRM_API_KEY == "la_tua_api_key_segreta":
        raise ValueError(
            "MRM_API_KEY non configurata. Imposta la variabile d'ambiente MRM_API_KEY "
            "o modifica la costante nello script."
        )

    # Costruzione del payload JSON
    payload: dict[str, str] = {
        "to": to,
        "subject": subject,
        "message": message,
    }

    # Aggiunta dei campi opzionali solo se specificati
    if from_email:
        payload["from"] = from_email
    if sender_name:
        payload["sender_name"] = sender_name
    if reply_to:
        payload["reply_to"] = reply_to

    # Headers della richiesta (API Key come secondo livello di autenticazione)
    headers: dict[str, str] = {
        "Content-Type": "application/json",
        "X-API-Key": MRM_API_KEY,
    }

    logger.info("Invio email a: %s | Oggetto: %s", to, subject)

    try:
        response: requests.Response = requests.post(
            API_ENDPOINT,
            json=payload,
            headers=headers,
            auth=(WP_USER, WP_APP_PASS),
            timeout=REQUEST_TIMEOUT,
        )

        # Solleva HTTPError per codici 4xx e 5xx
        response.raise_for_status()

        result: dict[str, Any] = response.json()
        logger.info(
            "Risposta dal server: [%d] %s",
            response.status_code,
            result.get("message", ""),
        )
        return result

    except ConnectionError as exc:
        logger.error(
            "Errore di connessione al server %s: %s",
            API_ENDPOINT,
            exc,
        )
        raise

    except Timeout as exc:
        logger.error(
            "Timeout dopo %d secondi per %s: %s",
            REQUEST_TIMEOUT,
            API_ENDPOINT,
            exc,
        )
        raise

    except HTTPError as exc:
        # Tenta di estrarre il messaggio di errore dalla risposta JSON
        error_detail: str = ""
        try:
            error_body: dict[str, Any] = exc.response.json()
            error_detail = error_body.get("message", str(error_body))
        except (ValueError, AttributeError):
            error_detail = exc.response.text if exc.response else str(exc)

        logger.error(
            "Errore HTTP %d: %s",
            exc.response.status_code if exc.response else 0,
            error_detail,
        )
        raise


# =============================================================================
# FUNZIONE DI INVIO BATCH
# =============================================================================


def send_batch_emails(
    emails: list[dict[str, str]],
    delay: float = BATCH_DELAY,
) -> list[dict[str, Any]]:
    """Invia una lista di email in sequenza con pausa tra un invio e l'altro.

    Utile per invii multipli rispettando il rate limiting del plugin
    (configurabile nella pagina Impostazioni > REST Mailer).

    Args:
        emails: Lista di dizionari, ciascuno con le chiavi accettate da
            send_email(). Chiavi obbligatorie: "to", "subject", "message".
            Chiavi opzionali: "from_email", "sender_name", "reply_to".
        delay: Pausa in secondi tra un invio e l'altro. Il valore predefinito
            e' BATCH_DELAY (2.0 secondi). Aumentare se il rate limit e' basso.

    Returns:
        Lista di dizionari con i risultati di ogni invio. Ogni elemento contiene:
        - "to": destinatario
        - "subject": oggetto
        - "success": True/False
        - "response": risposta JSON del server oppure messaggio di errore
    """
    results: list[dict[str, Any]] = []
    total: int = len(emails)

    logger.info("Inizio invio batch: %d email da inviare", total)

    for index, email_data in enumerate(emails, start=1):
        logger.info("--- Email %d/%d ---", index, total)

        result: dict[str, Any] = {
            "to": email_data.get("to", ""),
            "subject": email_data.get("subject", ""),
            "success": False,
            "response": None,
        }

        try:
            response: dict[str, Any] = send_email(
                to=email_data["to"],
                subject=email_data["subject"],
                message=email_data["message"],
                from_email=email_data.get("from_email"),
                sender_name=email_data.get("sender_name"),
                reply_to=email_data.get("reply_to"),
            )
            result["success"] = response.get("status") == "success"
            result["response"] = response

        except (ConnectionError, Timeout, HTTPError, ValueError) as exc:
            result["response"] = str(exc)
            logger.warning(
                "Invio fallito per %s: %s",
                email_data.get("to", "?"),
                exc,
            )

        results.append(result)

        # Pausa tra un invio e l'altro (tranne dopo l'ultimo)
        if index < total:
            logger.info("Attesa di %.1f secondi prima del prossimo invio...", delay)
            time.sleep(delay)

    # Riepilogo
    succeeded: int = sum(1 for r in results if r["success"])
    failed: int = total - succeeded
    logger.info(
        "Invio batch completato: %d/%d riusciti, %d falliti",
        succeeded,
        total,
        failed,
    )

    return results


# =============================================================================
# ESEMPI DI UTILIZZO
# =============================================================================


def example_single_email() -> None:
    """Esempio: invio di una singola email con tutti i campi."""
    logger.info("=== ESEMPIO: Invio singola email ===")

    try:
        result: dict[str, Any] = send_email(
            to="destinatario@example.com",
            subject="Test da Python - My REST Mailer",
            message=(
                "<h2>Ciao dal Python!</h2>"
                "<p>Questa email e' stata inviata tramite lo script "
                "<code>python-example.py</code>.</p>"
                "<p>Il plugin <strong>My REST Mailer</strong> supporta HTML completo.</p>"
            ),
            from_email="mittente@example.com",
            sender_name="Script Python",
            reply_to="risposte@example.com",
        )

        if result.get("status") == "success":
            logger.info("Email inviata con successo!")
        else:
            logger.warning("Risposta inattesa: %s", result)

    except (ConnectionError, Timeout) as exc:
        logger.error("Problema di rete: %s", exc)
    except HTTPError as exc:
        logger.error("Il server ha restituito un errore: %s", exc)
    except ValueError as exc:
        logger.error("Errore di configurazione: %s", exc)


def example_batch_emails() -> None:
    """Esempio: invio batch di piu' email da una lista."""
    logger.info("=== ESEMPIO: Invio batch di email ===")

    # Lista di email da inviare.
    # Ogni dizionario deve contenere almeno: to, subject, message.
    # I campi opzionali (from_email, sender_name, reply_to) sono facoltativi.
    email_list: list[dict[str, str]] = [
        {
            "to": "alice@example.com",
            "subject": "Notifica per Alice",
            "message": "<p>Ciao <strong>Alice</strong>, questo e' un messaggio automatico.</p>",
        },
        {
            "to": "bob@example.com",
            "subject": "Notifica per Bob",
            "message": "<p>Ciao <strong>Bob</strong>, questo e' un messaggio automatico.</p>",
            "sender_name": "Sistema Notifiche",
        },
        {
            "to": "charlie@example.com, dave@example.com",
            "subject": "Notifica di gruppo",
            "message": (
                "<p>Ciao <strong>Charlie</strong> e <strong>Dave</strong>,</p>"
                "<p>Questa e' una notifica inviata a destinatari multipli.</p>"
            ),
            "from_email": "notifiche@example.com",
            "reply_to": "supporto@example.com",
        },
    ]

    try:
        results: list[dict[str, Any]] = send_batch_emails(
            emails=email_list,
            delay=2.0,  # 2 secondi tra un invio e l'altro
        )

        # Stampa un riepilogo dettagliato
        logger.info("--- Riepilogo Batch ---")
        for i, res in enumerate(results, start=1):
            status_label: str = "OK" if res["success"] else "FALLITO"
            logger.info(
                "  %d. [%s] %s -> %s",
                i,
                status_label,
                res["subject"],
                res["to"],
            )

    except ValueError as exc:
        logger.error("Errore di configurazione: %s", exc)


# =============================================================================
# ENTRY POINT
# =============================================================================

if __name__ == "__main__":
    logger.info("My REST Mailer — Client Python")
    logger.info("Endpoint: %s", API_ENDPOINT)
    logger.info("")

    # --- Esempio 1: Invio singolo ---
    example_single_email()

    logger.info("")

    # --- Esempio 2: Invio batch ---
    example_batch_emails()

    logger.info("")
    logger.info("Script completato.")
