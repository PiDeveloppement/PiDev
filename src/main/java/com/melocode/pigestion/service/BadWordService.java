package com.melocode.pigestion.service;

import java.net.URI;
import java.net.URLEncoder;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;

public class BadWordService {
    private static final String API_URL = "https://www.purgomalum.com/service/plain?text=";

    public String filtrerTexte(String texte) {
        if (texte == null || texte.isEmpty()) return texte;
        try {
            // Encode le texte pour les espaces et caractères spéciaux
            String encodedTexte = URLEncoder.encode(texte, StandardCharsets.UTF_8);

            HttpClient client = HttpClient.newHttpClient();
            HttpRequest request = HttpRequest.newBuilder()
                    .uri(URI.create(API_URL + encodedTexte))
                    .build();

            HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString());
            return response.body(); // Retourne le texte filtré
        } catch (Exception e) {
            System.out.println("Erreur API BadWords : " + e.getMessage());
            return texte; // En cas d'erreur, on garde le texte original
        }
    }
}