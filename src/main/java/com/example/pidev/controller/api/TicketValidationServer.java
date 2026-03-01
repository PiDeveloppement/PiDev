package com.example.pidev.controller.api;

import com.example.pidev.model.event.EventTicket;
import com.example.pidev.service.event.EventTicketService;
import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpHandler;
import com.sun.net.httpserver.HttpServer;

import java.io.IOException;
import java.io.OutputStream;
import java.net.InetSocketAddress;
import java.net.URLDecoder;
import java.nio.charset.StandardCharsets;
import java.util.HashMap;
import java.util.Map;

/**
 * Serveur HTTP simple pour la validation des billets via scan QR
 * @author Ons Abdesslem
 */
public class TicketValidationServer {

    private static HttpServer server;
    private static EventTicketService ticketService;

    /**
     * Démarre le serveur HTTP sur le port 8080
     */
    public static void start() {
        try {
            ticketService = new EventTicketService();
            server = HttpServer.create(new InetSocketAddress(8080), 0);

            // Route : GET /validate?code=XXX
            server.createContext("/validate", new ValidateHandler());

            // Route : GET /ticket/{code}/pdf
            server.createContext("/ticket", new TicketPDFHandler());

            server.setExecutor(null);
            server.start();

            System.out.println("✅ Serveur de validation démarré sur http://localhost:8080");

        } catch (IOException e) {
            System.err.println("❌ Erreur démarrage serveur : " + e.getMessage());
            e.printStackTrace();
        }
    }

    /**
     * Arrête le serveur
     */
    public static void stop() {
        if (server != null) {
            server.stop(0);
            System.out.println("🛑 Serveur de validation arrêté");
        }
    }

    /**
     * Handler pour valider un billet (organisateur scanne le QR)
     */
    static class ValidateHandler implements HttpHandler {
        @Override
        public void handle(HttpExchange exchange) throws IOException {
            // CORS
            exchange.getResponseHeaders().add("Access-Control-Allow-Origin", "*");

            Map<String, String> params = parseQuery(exchange.getRequestURI().getQuery());
            String code = params.get("code");

            if (code == null || code.trim().isEmpty()) {
                sendHtmlResponse(exchange, 400, generateErrorPage("Code invalide", "Aucun code fourni"));
                return;
            }

            // Récupérer le billet
            EventTicket ticket = ticketService.getTicketByCode(code);

            if (ticket == null) {
                sendHtmlResponse(exchange, 404, generateErrorPage("Billet invalide", "Ce billet n'existe pas"));
                return;
            }

            if (ticket.isUsed()) {
                sendHtmlResponse(exchange, 200, generateAlreadyUsedPage(ticket));
                return;
            }

            // Marquer comme utilisé
            boolean updated = ticketService.markTicketAsUsed(ticket.getId());

            if (updated) {
                sendHtmlResponse(exchange, 200, generateSuccessPage(ticket));
            } else {
                sendHtmlResponse(exchange, 500, generateErrorPage("Erreur système", "Impossible de valider le billet"));
            }
        }
    }

    /**
     * Handler pour servir le PDF du billet
     */
    static class TicketPDFHandler implements HttpHandler {
        @Override
        public void handle(HttpExchange exchange) throws IOException {
            String path = exchange.getRequestURI().getPath();

            // Extraire le code du ticket depuis l'URL : /ticket/{code}/pdf
            String[] parts = path.split("/");
            if (parts.length < 4) {
                sendHtmlResponse(exchange, 400, generateErrorPage("URL invalide", "Format attendu : /ticket/{code}/pdf"));
                return;
            }

            String code = URLDecoder.decode(parts[2], StandardCharsets.UTF_8);

            // Récupérer le billet
            EventTicket ticket = ticketService.getTicketByCode(code);

            if (ticket == null) {
                sendHtmlResponse(exchange, 404, generateErrorPage("Billet introuvable", "Ce code n'existe pas"));
                return;
            }

            // Rediriger vers la page de téléchargement
            sendHtmlResponse(exchange, 200, generateDownloadPage(ticket));
        }
    }

    /**
     * Page HTML de succès
     */
    private static String generateSuccessPage(EventTicket ticket) {
        return "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Entrée autorisée</title><style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#10b981 0%,#059669 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}.container{background:white;border-radius:20px;padding:40px;max-width:500px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.3);text-align:center}.icon{width:100px;height:100px;border-radius:50%;background:#d1fae5;margin:0 auto 30px;display:flex;align-items:center;justify-content:center;font-size:50px}h1{color:#065f46;font-size:28px;margin-bottom:15px}p{color:#64748b;font-size:16px;line-height:1.6;margin-bottom:10px}.code{background:#f1f5f9;padding:15px;border-radius:10px;margin-top:20px;font-family:'Courier New',monospace;font-weight:bold;color:#0f172a}.footer{margin-top:30px;padding-top:20px;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:14px}</style></head><body><div class='container'><div class='icon'>✅</div><h1>Entrée autorisée !</h1><p>Le billet a été validé avec succès.</p><p>Le participant peut entrer à l'événement.</p><div class='code'>Code : " + ticket.getTicketCode() + "</div><div class='footer'><p>EventFlow - Validation</p><p>" + java.time.LocalDateTime.now().format(java.time.format.DateTimeFormatter.ofPattern("dd/MM/yyyy HH:mm:ss")) + "</p></div></div></body></html>";
    }

    /**
     * Page HTML déjà utilisé
     */
    private static String generateAlreadyUsedPage(EventTicket ticket) {
        return "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Billet déjà utilisé</title><style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}.container{background:white;border-radius:20px;padding:40px;max-width:500px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.3);text-align:center}.icon{width:100px;height:100px;border-radius:50%;background:#fef3c7;margin:0 auto 30px;display:flex;align-items:center;justify-content:center;font-size:50px}h1{color:#92400e;font-size:28px;margin-bottom:15px}p{color:#64748b;font-size:16px;line-height:1.6;margin-bottom:10px}.code{background:#f1f5f9;padding:15px;border-radius:10px;margin-top:20px;font-family:'Courier New',monospace;font-weight:bold;color:#0f172a}.footer{margin-top:30px;padding-top:20px;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:14px}</style></head><body><div class='container'><div class='icon'>⚠️</div><h1>Billet déjà utilisé</h1><p>Ce billet a déjà été scanné le " + ticket.getFormattedUsedAt() + ".</p><p>Accès refusé.</p><div class='code'>Code : " + ticket.getTicketCode() + "</div><div class='footer'><p>EventFlow - Validation</p><p>" + java.time.LocalDateTime.now().format(java.time.format.DateTimeFormatter.ofPattern("dd/MM/yyyy HH:mm:ss")) + "</p></div></div></body></html>";
    }

    /**
     * Page HTML d'erreur
     */
    private static String generateErrorPage(String title, String message) {
        return "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>" + title + "</title><style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#ef4444 0%,#dc2626 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}.container{background:white;border-radius:20px;padding:40px;max-width:500px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.3);text-align:center}.icon{width:100px;height:100px;border-radius:50%;background:#fee2e2;margin:0 auto 30px;display:flex;align-items:center;justify-content:center;font-size:50px}h1{color:#991b1b;font-size:28px;margin-bottom:15px}p{color:#64748b;font-size:16px;line-height:1.6;margin-bottom:10px}.footer{margin-top:30px;padding-top:20px;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:14px}</style></head><body><div class='container'><div class='icon'>❌</div><h1>" + title + "</h1><p>" + message + "</p><div class='footer'><p>EventFlow - Validation</p><p>" + java.time.LocalDateTime.now().format(java.time.format.DateTimeFormatter.ofPattern("dd/MM/yyyy HH:mm:ss")) + "</p></div></div></body></html>";
    }

    /**
     * Page de téléchargement du PDF
     */
    private static String generateDownloadPage(EventTicket ticket) {
        return "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Télécharger le billet</title><style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}.container{background:white;border-radius:20px;padding:40px;max-width:500px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.3);text-align:center}.icon{width:100px;height:100px;border-radius:50%;background:#dbeafe;margin:0 auto 30px;display:flex;align-items:center;justify-content:center;font-size:50px}h1{color:#0D47A1;font-size:28px;margin-bottom:15px}p{color:#64748b;font-size:16px;line-height:1.6;margin-bottom:10px}.code{background:#f1f5f9;padding:15px;border-radius:10px;margin-top:20px;font-family:'Courier New',monospace;font-weight:bold;color:#0f172a}.footer{margin-top:30px;padding-top:20px;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:14px}</style></head><body><div class='container'><div class='icon'>🎫</div><h1>Votre billet</h1><p>Code : " + ticket.getTicketCode() + "</p><p>Pour télécharger le PDF, utilisez l'application EventFlow.</p><div class='footer'><p>EventFlow</p></div></div></body></html>";
    }

    /**
     * Parse les paramètres de l'URL
     */
    private static Map<String, String> parseQuery(String query) {
        Map<String, String> params = new HashMap<>();
        if (query != null && !query.isEmpty()) {
            for (String pair : query.split("&")) {
                String[] kv = pair.split("=");
                if (kv.length == 2) {
                    params.put(
                        URLDecoder.decode(kv[0], StandardCharsets.UTF_8),
                        URLDecoder.decode(kv[1], StandardCharsets.UTF_8)
                    );
                }
            }
        }
        return params;
    }

    /**
     * Envoie une réponse HTML
     */
    private static void sendHtmlResponse(HttpExchange exchange, int statusCode, String html) throws IOException {
        exchange.getResponseHeaders().set("Content-Type", "text/html; charset=UTF-8");
        byte[] response = html.getBytes(StandardCharsets.UTF_8);
        exchange.sendResponseHeaders(statusCode, response.length);
        OutputStream os = exchange.getResponseBody();
        os.write(response);
        os.close();
    }
}

