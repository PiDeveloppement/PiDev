package com.example.pidev.service.external;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.Response;

import java.io.IOException;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;

public class OpenStreetMapService {

    private final OkHttpClient client = new OkHttpClient();

    public String searchCompany(String companyName) throws IOException {
        String query = URLEncoder.encode(companyName + " Tunisie", StandardCharsets.UTF_8);
        String url = "https://nominatim.openstreetmap.org/search?q=" + query
                + "&format=json&addressdetails=1&limit=1";

        Request request = new Request.Builder()
                .url(url)
                .header("User-Agent", "EventFlowApp/1.0 (manaimaryem4@gmail.com)")
                .build();

        try (Response response = client.newCall(request).execute()) {
            if (!response.isSuccessful()) return null;
            String json = response.body().string();
            JsonArray results = JsonParser.parseString(json).getAsJsonArray();
            if (results.size() == 0) return null;
            JsonObject first = results.get(0).getAsJsonObject();
            return first.get("display_name").getAsString();
        }
    }
}