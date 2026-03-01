package com.example.pidev.service.external;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class TextRazorService {

    private static final String API_KEY = "b3e5dafb062159df23137c1c5b553854a4eb369b230e450440293457";
    private static final String API_URL = "https://api.textrazor.com/";

    /**
     * Extrait les mots-clés d'un texte via l'API TextRazor.
     * En cas d'échec, effectue un fallback simple (découpage en mots).
     */
    public List<String> extractKeywords(String text) {
        if (text == null || text.trim().isEmpty()) return new ArrayList<>();

        try {
            URL url = new URL(API_URL);
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded");
            conn.setRequestProperty("X-TextRazor-Key", API_KEY);
            conn.setDoOutput(true);

            String params = "text=" + java.net.URLEncoder.encode(text, StandardCharsets.UTF_8)
                    + "&extractors=entities,words,phrases";

            try (OutputStream os = conn.getOutputStream()) {
                os.write(params.getBytes());
                os.flush();
            }

            int responseCode = conn.getResponseCode();
            if (responseCode != 200) {
                System.err.println("TextRazor error: " + responseCode + " - fallback vers découpage simple");
                return fallbackKeywords(text);
            }

            BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream()));
            StringBuilder response = new StringBuilder();
            String line;
            while ((line = in.readLine()) != null) response.append(line);
            in.close();

            JsonObject json = JsonParser.parseString(response.toString()).getAsJsonObject();
            JsonObject responseObj = json.getAsJsonObject("response");
            if (responseObj == null) return fallbackKeywords(text);

            List<String> keywords = new ArrayList<>();

            JsonArray entities = responseObj.getAsJsonArray("entities");
            if (entities != null) {
                for (int i = 0; i < entities.size(); i++) {
                    JsonObject entity = entities.get(i).getAsJsonObject();
                    keywords.add(entity.get("entityId").getAsString().toLowerCase());
                }
            }

            JsonArray phrases = responseObj.getAsJsonArray("phrases");
            if (phrases != null) {
                for (int i = 0; i < phrases.size(); i++) {
                    JsonObject phrase = phrases.get(i).getAsJsonObject();
                    keywords.add(phrase.get("word").getAsString().toLowerCase());
                }
            }

            return keywords.stream().distinct().toList();

        } catch (Exception e) {
            System.err.println("Exception TextRazor: " + e.getMessage() + " - fallback vers découpage simple");
            return fallbackKeywords(text);
        }
    }

    /**
     * Fallback simple : découpe le texte en mots et filtre les mots courts.
     */
    private List<String> fallbackKeywords(String text) {
        return Arrays.stream(text.toLowerCase().split("[,\\s]+"))
                .filter(k -> k.length() > 2)
                .distinct()
                .toList();
    }

    /**
     * Calcule un score de similarité entre deux listes de mots-clés.
     */
    public static double similarityScore(List<String> keywords1, List<String> keywords2) {
        if (keywords1.isEmpty() || keywords2.isEmpty()) return 0;
        long common = keywords1.stream().filter(keywords2::contains).count();
        return (double) common / Math.max(keywords1.size(), keywords2.size());
    }
}