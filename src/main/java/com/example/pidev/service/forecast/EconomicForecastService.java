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
 * Service de prÃ©vision Ã©conomique utilisant ExchangeRate-API
 * ClÃ© API : 2a91c695078b4c027b91e11d
 */
public class EconomicForecastService {

    private static final String API_KEY = "2a91c695078b4c027b91e11d";
    private static final String BASE_URL = "https://v6.exchangerate-api.com/v6/" + API_KEY + "/latest/";

    // Cache simple pour Ã©viter des appels inutiles (valable 24h)
    private JsonObject cachedRates;
    private String cachedBase;
    private LocalDate cacheDate;

    private final HttpClient httpClient = HttpClient.newHttpClient();

    /**
     * RÃ©cupÃ¨re tous les taux de change pour une devise de base
     */
    private JsonObject fetchRates(String baseCurrency) {
        // VÃ©rifier le cache (valable 24h)
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

                    System.out.println("âœ… Taux de change rÃ©cupÃ©rÃ©s pour " + baseCurrency + " le " +
                            LocalDate.now().format(DateTimeFormatter.ofPattern("dd/MM/yyyy", Locale.FRENCH)));
                    return rates;
                }
            }
        } catch (Exception e) {
            System.err.println("âŒ Erreur API ExchangeRate: " + e.getMessage());
        }
        return null;
    }

    /**
     * RÃ©cupÃ¨re le taux de change entre deux devises
     * @param from devise source (ex: "USD", "EUR", "TND")
     * @param to devise cible
     * @return le taux de change, ou -1 en cas d'erreur
     */
    public double getExchangeRate(String from, String to) {
        // Normaliser les codes devises (en majuscules)
        from = from.toUpperCase();
        to = to.toUpperCase();

        // Si c'est la mÃªme devise, taux = 1
        if (from.equals(to)) return 1.0;

        // RÃ©cupÃ©rer les taux pour la devise source
        JsonObject rates = fetchRates(from);
        if (rates != null && rates.has(to)) {
            return rates.get(to).getAsDouble();
        }

        // Si Ã©chec, essayer via USD comme pivot (car API gratuite ne supporte pas toutes les combinaisons)
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
     * Calcule un facteur d'ajustement basÃ© sur la variation annuelle estimÃ©e d'une devise
     * @param currency la devise Ã  Ã©valuer
     * @return facteur d'ajustement (ex: 0.97 pour une dÃ©prÃ©ciation de 3%)
     */
    public double getAnnualAdjustmentFactor(String currency) {
        // Approximation : on utilise le taux de change USD/Devise comme indicateur
        // Plus la devise est faible par rapport au USD, plus l'inflation est probable
        // Ceci est une simplification - en rÃ©alitÃ©, il faudrait des donnÃ©es historiques
        try {
            JsonObject usdRates = fetchRates("USD");
            if (usdRates != null && usdRates.has(currency)) {
                double rate = usdRates.get(currency).getAsDouble();

                // Pour les devises faibles (> 100 pour 1 USD), on suppose une inflation plus forte
                if (rate > 1000) return 0.85;      // DÃ©prÃ©ciation forte (ex: IRR)
                if (rate > 100) return 0.92;       // DÃ©prÃ©ciation modÃ©rÃ©e
                if (rate > 10) return 0.97;        // LÃ©gÃ¨re dÃ©prÃ©ciation
                if (rate < 0.01) return 1.08;      // ApprÃ©ciation forte
                if (rate < 0.1) return 1.03;        // LÃ©gÃ¨re apprÃ©ciation
                return 1.0;                          // Stable
            }
        } catch (Exception e) {
            // IgnorÃ©
        }
        return 1.0; // Pas d'ajustement par dÃ©faut
    }

    /**
     * Estime le pouvoir d'achat futur d'un montant aprÃ¨s un certain nombre de jours,
     * en utilisant l'ajustement basÃ© sur la force de la devise.
     * @param amount montant actuel
     * @param days nombre de jours dans le futur
     * @param currency devise du montant (ex: "TND")
     * @return montant ajustÃ©
     */
    public double adjustForInflation(double amount, int days, String currency) {
        double years = days / 365.0;
        double adjustmentFactor = getAnnualAdjustmentFactor(currency);

        // Appliquer le facteur d'ajustement annuel composÃ©
        return amount * Math.pow(adjustmentFactor, years);
    }

    /**
     * Convertit un montant dans une devise future en Ã©quivalent TND actuel
     * en tenant compte de l'inflation estimÃ©e.
     */
    public double convertFutureToCurrentTND(double futureAmount, int days, String fromCurrency) {
        // Ã‰tape 1 : Ajuster pour l'inflation dans la devise source
        double adjustedInSource = adjustForInflation(futureAmount, days, fromCurrency);

        // Ã‰tape 2 : Convertir en TND au taux actuel
        double rate = getExchangeRate(fromCurrency, "TND");
        if (rate > 0) {
            return adjustedInSource * rate;
        }
        return -1;
    }

    /**
     * Nombre de participants necessaires pour atteindre un objectif de recettes.
     * Formule: ceil(max(0, objectif - recettesActuelles) / prixBillet)
     */
    public long calculateParticipantsToReachTarget(double targetRevenue, double currentRevenue, double ticketPrice) {
        if (ticketPrice <= 0) {
            return -1;
        }
        double missing = Math.max(0, targetRevenue - currentRevenue);
        return (long) Math.ceil(missing / ticketPrice);
    }

    /**
     * Nombre de participants necessaires pour couvrir le deficit (depenses > recettes).
     * Formule: ceil(max(0, depenses - recettesActuelles) / prixBillet)
     */
    public long calculateParticipantsToCoverDeficit(double totalExpenses, double currentRevenue, double ticketPrice) {
        if (ticketPrice <= 0) {
            return -1;
        }
        double missing = Math.max(0, totalExpenses - currentRevenue);
        return (long) Math.ceil(missing / ticketPrice);
    }
}