package com.example.pidev.service.vision;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.util.Base64;

public class VisionService {
    // Remplacez par votre clé API Google Vision
    private static final String API_KEY = "AIzaSyBMoeP1FUqpoXig4pvdZu0oKkM6RWO6L84";
    private static final String API_URL = "https://vision.googleapis.com/v1/images:annotate?key=" + API_KEY;

    /**
     * Analyse une image à partir de son URL publique.
     * @param imageUrl URL publique de l'image
     * @return Liste des labels détectés, ou null en cas d'erreur
     */
    public static JsonArray analyzeImageFromUrl(String imageUrl) {
        try {
            HttpClient client = HttpClient.newHttpClient();
            JsonObject requestBody = new JsonObject();
            JsonObject image = new JsonObject();
            JsonObject source = new JsonObject();
            source.addProperty("imageUri", imageUrl);
            image.add("source", source);
            JsonObject feature = new JsonObject();
            feature.addProperty("type", "LABEL_DETECTION");
            feature.addProperty("maxResults", 10);
            JsonArray features = new JsonArray();
            features.add(feature);
            JsonObject request = new JsonObject();
            request.add("image", image);
            request.add("features", features);
            JsonArray requests = new JsonArray();
            requests.add(request);
            requestBody.add("requests", requests);

            HttpRequest httpRequest = HttpRequest.newBuilder()
                    .uri(URI.create(API_URL))
                    .header("Content-Type", "application/json")
                    .POST(HttpRequest.BodyPublishers.ofString(requestBody.toString()))
                    .build();

            HttpResponse<String> response = client.send(httpRequest, HttpResponse.BodyHandlers.ofString());
            System.out.println("=== Vision API Response (URL) ===");
            System.out.println("Status code: " + response.statusCode());
            System.out.println("Body: " + response.body());
            System.out.println("================================");

            if (response.statusCode() == 200) {
                JsonObject json = JsonParser.parseString(response.body()).getAsJsonObject();
                JsonArray responses = json.getAsJsonArray("responses");
                if (responses.size() > 0) {
                    JsonObject firstResponse = responses.get(0).getAsJsonObject();
                    if (firstResponse.has("labelAnnotations")) {
                        return firstResponse.getAsJsonArray("labelAnnotations");
                    } else if (firstResponse.has("error")) {
                        System.err.println("Erreur API dans la réponse : " + firstResponse.getAsJsonObject("error"));
                    }
                }
            } else {
                System.err.println("Erreur HTTP : " + response.statusCode() + " - " + response.body());
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
        return null;
    }

    /**
     * Analyse une image à partir de son contenu binaire (upload).
     * @param imageBytes tableau de bytes de l'image
     * @return Liste des labels détectés, ou null en cas d'erreur
     */
    public static JsonArray analyzeImageFromBytes(byte[] imageBytes) {
        try {
            String base64Image = Base64.getEncoder().encodeToString(imageBytes);
            HttpClient client = HttpClient.newHttpClient();
            JsonObject requestBody = new JsonObject();
            JsonObject image = new JsonObject();
            image.addProperty("content", base64Image);
            JsonObject feature = new JsonObject();
            feature.addProperty("type", "LABEL_DETECTION");
            feature.addProperty("maxResults", 10);
            JsonArray features = new JsonArray();
            features.add(feature);
            JsonObject request = new JsonObject();
            request.add("image", image);
            request.add("features", features);
            JsonArray requests = new JsonArray();
            requests.add(request);
            requestBody.add("requests", requests);

            HttpRequest httpRequest = HttpRequest.newBuilder()
                    .uri(URI.create(API_URL))
                    .header("Content-Type", "application/json")
                    .POST(HttpRequest.BodyPublishers.ofString(requestBody.toString()))
                    .build();

            HttpResponse<String> response = client.send(httpRequest, HttpResponse.BodyHandlers.ofString());
            System.out.println("=== Vision API Response (Bytes) ===");
            System.out.println("Status code: " + response.statusCode());
            System.out.println("Body: " + response.body());
            System.out.println("==================================");

            if (response.statusCode() == 200) {
                JsonObject json = JsonParser.parseString(response.body()).getAsJsonObject();
                JsonArray responses = json.getAsJsonArray("responses");
                if (responses.size() > 0) {
                    JsonObject firstResponse = responses.get(0).getAsJsonObject();
                    if (firstResponse.has("labelAnnotations")) {
                        return firstResponse.getAsJsonArray("labelAnnotations");
                    } else if (firstResponse.has("error")) {
                        System.err.println("Erreur API dans la réponse : " + firstResponse.getAsJsonObject("error"));
                    }
                }
            } else {
                System.err.println("Erreur HTTP : " + response.statusCode() + " - " + response.body());
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
        return null;
    }
}