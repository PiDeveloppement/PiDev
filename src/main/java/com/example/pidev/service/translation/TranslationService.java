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
    private static final String API_URL = "http://api.mymemory.translated.net/get";
    private static final HttpClient client = HttpClient.newHttpClient();
    private static final Map<String, Map<String, String>> cache = new HashMap<>();
    private static String currentLang = "fr";

    public static void setCurrentLang(String lang) {
        currentLang = lang;
    }

    /**
     * Traduit un texte du français vers la langue courante.
     * @param text le texte à traduire (en français)
     * @return le texte traduit, ou le texte original en cas d'erreur
     */
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
            String url = API_URL + "?q=" + encodedText + "&langpair=fr|" + currentLang;
            HttpRequest request = HttpRequest.newBuilder()
                    .uri(URI.create(url))
                    .GET()
                    .build();

            HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString());
            if (response.statusCode() == 200) {
                JsonObject json = JsonParser.parseString(response.body()).getAsJsonObject();
                String translated = json.get("responseData").getAsJsonObject().get("translatedText").getAsString();
                // Mettre en cache
                cache.computeIfAbsent(currentLang, k -> new HashMap<>()).put(text, translated);
                return translated;
            } else {
                System.err.println("Erreur traduction: " + response.statusCode());
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
        return text;
    }
}