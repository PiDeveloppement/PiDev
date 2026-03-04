package com.example.pidev.service.forecast;

import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import java.io.IOException;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.util.Locale;

/**
 * Service de prévision économique utilisant ExchangeRate-API
 * Clé API : 2a91c695078b4c027b91e11d
 */
public class EconomicForecastService {

    private static final String API_KEY = "2a91c695078b4c027b91e11d";
    private static final String BASE_URL = "https://v6.exchangerate-api.com/v6/" + API_KEY + "/latest/";

    // Cache simple pour éviter des appels inutiles (valable 24h)
    private JsonObject cachedRates;
    private String cachedBase;
    private LocalDate cacheDate;

    private final HttpClient httpClient = HttpClient.newHttpClient();

    /**
     * Récupère tous les taux de change pour une devise de base
     */
    private JsonObject fetchRates(String baseCurrency) {
        // Vérifier le cache (valable 24h)
        if (cachedRates != null && cachedBase != null && cachedBase.equals(baseCurrency)
                && cacheDate != null && cacheDate.equals(LocalDate.now())) {
            return cachedRates;
        }

        try {
            String url = BASE_URL + baseCurrency;
            HttpRequest request = HttpRequest.newBuilder()
                    .uri(URI.create(url))
                    .GET()
                    .header("Accept", "application/json")
                    .build();

            HttpResponse<String> response = httpClient.send(request, HttpResponse.BodyHandlers.ofString());

            if (response.statusCode() == 200) {
                JsonObject json = JsonParser.parseString(response.body()).getAsJsonObject();
                String result = json.get("result").getAsString();

                if ("success".equals(result)) {
                    JsonObject rates = json.getAsJsonObject("conversion_rates");
                    // Mettre en cache
                    cachedRates = rates;
                    cachedBase = baseCurrency;
                    cacheDate = LocalDate.now();

                    System.out.println("✅ Taux de change récupérés pour " + baseCurrency + " le " +
                            LocalDate.now().format(DateTimeFormatter.ofPattern("dd/MM/yyyy", Locale.FRENCH)));
                    return rates;
                }
            }
        } catch (Exception e) {
            System.err.println("❌ Erreur API ExchangeRate: " + e.getMessage());
        }
        return null;
    }

    /**
     * Récupère le taux de change entre deux devises
     * @param from devise source (ex: "USD", "EUR", "TND")
     * @param to devise cible
     * @return le taux de change, ou -1 en cas d'erreur
     */
    public double getExchangeRate(String from, String to) {
        // Normaliser les codes devises (en majuscules)
        from = from.toUpperCase();
        to = to.toUpperCase();

        // Si c'est la même devise, taux = 1
        if (from.equals(to)) return 1.0;

        // Récupérer les taux pour la devise source
        JsonObject rates = fetchRates(from);
        if (rates != null && rates.has(to)) {
            return rates.get(to).getAsDouble();
        }

        // Si échec, essayer via USD comme pivot (car API gratuite ne supporte pas toutes les combinaisons)
        if (!from.equals("USD") && !to.equals("USD")) {
            JsonObject usdRates = fetchRates("USD");
            if (usdRates != null && usdRates.has(from) && usdRates.has(to)) {
                double fromToUsd = usdRates.get(from).getAsDouble();
                double usdToTarget = usdRates.get(to).getAsDouble();
                return usdToTarget / fromToUsd;
            }
        }

        return -1;
    }

    /**
     * Calcule un facteur d'ajustement basé sur la variation annuelle estimée d'une devise
     * @param currency la devise à évaluer
     * @return facteur d'ajustement (ex: 0.97 pour une dépréciation de 3%)
     */
    public double getAnnualAdjustmentFactor(String currency) {
        // Approximation : on utilise le taux de change USD/Devise comme indicateur
        // Plus la devise est faible par rapport au USD, plus l'inflation est probable
        // Ceci est une simplification - en réalité, il faudrait des données historiques
        try {
            JsonObject usdRates = fetchRates("USD");
            if (usdRates != null && usdRates.has(currency)) {
                double rate = usdRates.get(currency).getAsDouble();

                // Pour les devises faibles (> 100 pour 1 USD), on suppose une inflation plus forte
                if (rate > 1000) return 0.85;      // Dépréciation forte (ex: IRR)
                if (rate > 100) return 0.92;       // Dépréciation modérée
                if (rate > 10) return 0.97;        // Légère dépréciation
                if (rate < 0.01) return 1.08;      // Appréciation forte
                if (rate < 0.1) return 1.03;        // Légère appréciation
                return 1.0;                          // Stable
            }
        } catch (Exception e) {
            // Ignoré
        }
        return 1.0; // Pas d'ajustement par défaut
    }

    /**
     * Estime le pouvoir d'achat futur d'un montant après un certain nombre de jours,
     * en utilisant l'ajustement basé sur la force de la devise.
     * @param amount montant actuel
     * @param days nombre de jours dans le futur
     * @param currency devise du montant (ex: "TND")
     * @return montant ajusté
     */
    public double adjustForInflation(double amount, int days, String currency) {
        double years = days / 365.0;
        double adjustmentFactor = getAnnualAdjustmentFactor(currency);

        // Appliquer le facteur d'ajustement annuel composé
        return amount * Math.pow(adjustmentFactor, years);
    }

    /**
     * Convertit un montant dans une devise future en équivalent TND actuel
     * en tenant compte de l'inflation estimée.
     */
    public double convertFutureToCurrentTND(double futureAmount, int days, String fromCurrency) {
        // Étape 1 : Ajuster pour l'inflation dans la devise source
        double adjustedInSource = adjustForInflation(futureAmount, days, fromCurrency);

        // Étape 2 : Convertir en TND au taux actuel
        double rate = getExchangeRate(fromCurrency, "TND");
        if (rate > 0) {
            return adjustedInSource * rate;
        }
        return -1;
    }
}