package com.example.pidev.service.whatsapp;

import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;

public class WhatsAppService {

    // Vos identifiants UltraMsg (fournis)
    private static final String INSTANCE_ID = "instance163163";
    private static final String TOKEN = "3philhykxwwpnb7k";
    private static final String API_URL = "https://api.ultramsg.com/" + INSTANCE_ID + "/messages/chat";

    /**
     * Envoie un message WhatsApp de confirmation de contribution.
     * @param phoneNumber Numéro au format international sans + (ex: 216XXXXXXXX)
     * @param company Nom de l'entreprise
     * @param amount Montant en TND
     * @return true si l'envoi a réussi, false sinon
     */
    public static boolean sendConfirmation(String phoneNumber, String company, double amount) {
        try {
            String message = String.format("Merci %s ! Votre contribution de %.2f TND a été enregistrée. - EventFlow",
                    company, amount);
            String encodedMessage = URLEncoder.encode(message, StandardCharsets.UTF_8);

            // Corps de la requête au format x-www-form-urlencoded
            String params = "token=" + TOKEN +
                    "&to=" + phoneNumber +
                    "&body=" + encodedMessage;

            HttpClient client = HttpClient.newHttpClient();
            HttpRequest request = HttpRequest.newBuilder()
                    .uri(URI.create(API_URL))
                    .header("Content-Type", "application/x-www-form-urlencoded")
                    .POST(HttpRequest.BodyPublishers.ofString(params))
                    .build();

            HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString());
            System.out.println("Réponse UltraMsg: " + response.body());
            return response.statusCode() == 200;
        } catch (Exception e) {
            e.printStackTrace();
            return false;
        }
    }
}