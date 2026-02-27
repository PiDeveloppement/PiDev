package com.example.pidev.service.translation;

import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import java.net.URI;
import java.net.URLEncoder;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.util.HashMap;
import java.util.Map;

public class TranslationService {
    private static final String API_URL = "https://api.mymemory.translated.net/get";
    private static final HttpClient client = HttpClient.newBuilder()
            .followRedirects(HttpClient.Redirect.ALWAYS)
            .build();
    private static final Map<String, Map<String, String>> cache = new HashMap<>();
    private static String currentLang = "fr";

    public static void setCurrentLang(String lang) {
        currentLang = lang;
    }

    public static String getCurrentLang() {
        return currentLang;
    }

    public static String translate(String text) {
        if (currentLang.equals("fr") || text == null || text.isEmpty()) {
            return text;
        }
        // Vérifier le cache
        if (cache.containsKey(currentLang) && cache.get(currentLang).containsKey(text)) {
            return cache.get(currentLang).get(text);
        }
        try {
            String encodedText = URLEncoder.encode(text, StandardCharsets.UTF_8);
            String langPair = "fr|" + currentLang;
            String encodedLangPair = URLEncoder.encode(langPair, StandardCharsets.UTF_8);
            String url = API_URL + "?q=" + encodedText + "&langpair=" + encodedLangPair;

            HttpRequest request = HttpRequest.newBuilder()
                    .uri(URI.create(url))
                    .header("User-Agent", "EventFlow-App/1.0")
                    .GET()
                    .build();

            HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString());

            if (response.statusCode() == 200) {
                JsonObject json = JsonParser.parseString(response.body()).getAsJsonObject();
                String translated = json.get("responseData").getAsJsonObject().get("translatedText").getAsString();
                // Nettoyer les éventuels marqueurs de confiance (ex: "###")
                translated = translated.replaceAll("^#+", "").trim();
                cache.computeIfAbsent(currentLang, k -> new HashMap<>()).put(text, translated);
                return translated;
            } else {
                System.err.println("Erreur traduction: " + response.statusCode());
                System.err.println("Réponse: " + response.body());
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
        return text;
    }
}