package com.example.pidev.service.imagga;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import java.io.File;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.file.Files;
import java.util.Base64;
import java.util.UUID;

public class ImaggaService {
    // Clés en dur (à ne pas commiter sur GitHub !)
    private static final String API_KEY = "acc_d20c8f63f05372d";
    private static final String API_SECRET = "293b0b9b1d668cf7024b85989dc7a4db";
    private static final String API_URL = "https://api.imagga.com/v2/tags";

    public static JsonArray analyzeImageFromFile(File imageFile) {
        try {
            String auth = API_KEY + ":" + API_SECRET;
            String encodedAuth = Base64.getEncoder().encodeToString(auth.getBytes());

            byte[] fileBytes = Files.readAllBytes(imageFile.toPath());
            String boundary = "----Boundary" + UUID.randomUUID();
            String CRLF = "\r\n";

            String part1 = "--" + boundary + CRLF +
                    "Content-Disposition: form-data; name=\"image\"; filename=\"" + imageFile.getName() + "\"" + CRLF +
                    "Content-Type: " + Files.probeContentType(imageFile.toPath()) + CRLF + CRLF;

            byte[] part1Bytes = part1.getBytes();
            byte[] endBytes = (CRLF + "--" + boundary + "--" + CRLF).getBytes();

            byte[] body = new byte[part1Bytes.length + fileBytes.length + endBytes.length];
            System.arraycopy(part1Bytes, 0, body, 0, part1Bytes.length);
            System.arraycopy(fileBytes, 0, body, part1Bytes.length, fileBytes.length);
            System.arraycopy(endBytes, 0, body, part1Bytes.length + fileBytes.length, endBytes.length);

            HttpClient client = HttpClient.newHttpClient();
            HttpRequest request = HttpRequest.newBuilder()
                    .uri(URI.create(API_URL))
                    .header("Content-Type", "multipart/form-data; boundary=" + boundary)
                    .header("Authorization", "Basic " + encodedAuth)
                    .POST(HttpRequest.BodyPublishers.ofByteArray(body))
                    .build();

            HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString());
            System.err.println("=== Imagga API Response ===");
            System.err.println("Status code: " + response.statusCode());
            System.err.println("Body: " + response.body());
            System.err.println("============================");

            if (response.statusCode() == 200) {
                JsonObject json = JsonParser.parseString(response.body()).getAsJsonObject();
                JsonObject result = json.getAsJsonObject("result");
                if (result != null && result.has("tags")) {
                    return result.getAsJsonArray("tags");
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