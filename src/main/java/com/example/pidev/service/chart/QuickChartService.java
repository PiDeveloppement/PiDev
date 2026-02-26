package com.example.pidev.service.chart;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;

public class QuickChartService {

    private static final String BASE_URL = "https://quickchart.io/chart?width=800&height=400&backgroundColor=white&c=";

    /**
     * Génère l'URL d'un graphique à partir d'une configuration JSON.
     */
    public static String getChartUrl(JsonObject config) {
        String json = config.toString();
        String encoded = URLEncoder.encode(json, StandardCharsets.UTF_8);
        return BASE_URL + encoded;
    }

    /**
     * Construit un graphique en barres avec plusieurs séries.
     */
    public static JsonObject createMultiBarChart(String title, String[] labels,
                                                 String[] seriesNames, double[][] seriesData) {
        JsonObject config = new JsonObject();
        config.addProperty("type", "bar");

        JsonObject data = new JsonObject();
        JsonArray labelsArray = new JsonArray();
        for (String l : labels) labelsArray.add(l);
        data.add("labels", labelsArray);

        JsonArray datasets = new JsonArray();
        String[] colors = {"#1E3A8A", "#F97316", "#10B981", "#7C3AED", "#EF4444"};
        for (int i = 0; i < seriesNames.length; i++) {
            JsonObject ds = new JsonObject();
            ds.addProperty("label", seriesNames[i]);
            JsonArray dataArray = new JsonArray();
            for (double val : seriesData[i]) dataArray.add(val);
            ds.add("data", dataArray);
            ds.addProperty("backgroundColor", colors[i % colors.length] + "80"); // 50% opacity
            ds.addProperty("borderColor", colors[i % colors.length]);
            ds.addProperty("borderWidth", 1);
            datasets.add(ds);
        }
        data.add("datasets", datasets);
        config.add("data", data);

        JsonObject options = new JsonObject();
        options.addProperty("responsive", true);
        JsonObject plugins = new JsonObject();
        JsonObject titleObj = new JsonObject();
        titleObj.addProperty("display", true);
        titleObj.addProperty("text", title);
        plugins.add("title", titleObj);
        options.add("plugins", plugins);
        config.add("options", options);

        return config;
    }

    /**
     * Construit un graphique en ligne avec plusieurs séries.
     */
    public static JsonObject createLineChart(String title, String[] labels,
                                             String[] seriesNames, double[][] seriesData) {
        JsonObject config = new JsonObject();
        config.addProperty("type", "line");

        JsonObject data = new JsonObject();
        JsonArray labelsArray = new JsonArray();
        for (String l : labels) labelsArray.add(l);
        data.add("labels", labelsArray);

        JsonArray datasets = new JsonArray();
        String[] colors = {"#1E3A8A", "#F97316", "#10B981", "#7C3AED"};
        for (int i = 0; i < seriesNames.length; i++) {
            JsonObject ds = new JsonObject();
            ds.addProperty("label", seriesNames[i]);
            JsonArray dataArray = new JsonArray();
            for (double val : seriesData[i]) dataArray.add(val);
            ds.add("data", dataArray);
            ds.addProperty("borderColor", colors[i % colors.length]);
            ds.addProperty("backgroundColor", "transparent");
            ds.addProperty("tension", 0.1);
            if (i == 1) { // projection en pointillés
                ds.addProperty("borderDash", "5,5");
            }
            datasets.add(ds);
        }
        data.add("datasets", datasets);
        config.add("data", data);

        JsonObject options = new JsonObject();
        options.addProperty("responsive", true);
        JsonObject plugins = new JsonObject();
        JsonObject titleObj = new JsonObject();
        titleObj.addProperty("display", true);
        titleObj.addProperty("text", title);
        plugins.add("title", titleObj);
        options.add("plugins", plugins);
        config.add("options", options);

        return config;
    }

    /**
     * Construit un graphique en anneau (doughnut).
     */
    public static JsonObject createDoughnutChart(String title, String[] labels, double[] data) {
        JsonObject config = new JsonObject();
        config.addProperty("type", "doughnut");

        JsonObject dataObj = new JsonObject();
        JsonArray labelsArray = new JsonArray();
        for (String l : labels) labelsArray.add(l);
        dataObj.add("labels", labelsArray);

        JsonArray datasets = new JsonArray();
        JsonObject dataset = new JsonObject();
        JsonArray dataArray = new JsonArray();
        for (double d : data) dataArray.add(d);
        dataset.add("data", dataArray);
        JsonArray bgColors = new JsonArray();
        bgColors.add("#1E3A8A");
        bgColors.add("#F97316");
        bgColors.add("#10B981");
        bgColors.add("#7C3AED");
        bgColors.add("#EF4444");
        dataset.add("backgroundColor", bgColors);
        datasets.add(dataset);
        dataObj.add("datasets", datasets);
        config.add("data", dataObj);

        JsonObject options = new JsonObject();
        options.addProperty("responsive", true);
        JsonObject plugins = new JsonObject();
        JsonObject titleObj = new JsonObject();
        titleObj.addProperty("display", true);
        titleObj.addProperty("text", title);
        plugins.add("title", titleObj);
        JsonObject legend = new JsonObject();
        legend.addProperty("position", "bottom");
        plugins.add("legend", legend);
        options.add("plugins", plugins);
        config.add("options", options);

        return config;
    }
}