module com.melocode.pigestion {
    // Modules standards JavaFX
    requires javafx.controls;
    requires javafx.fxml;
    requires javafx.base;

    // Modules pour la base de données et le réseau
    requires java.sql;
    requires mysql.connector.j;
    requires java.net.http;

    // --- AJOUTS INDISPENSABLES ---
    requires atlantafx.base; // Règle l'erreur "Cannot resolve symbol atlantafx"
    requires itextpdf;       // Pour la gestion des PDF
    // -----------------------------

    // Ouverture des packages pour JavaFX (Reflexion)
    opens com.melocode.pigestion to javafx.fxml;
    opens com.melocode.pigestion.controller to javafx.fxml;
    opens com.melocode.pigestion.model to javafx.base;
    opens com.melocode.pigestion.service to javafx.fxml;
    opens com.melocode.pigestion.controller.auth to javafx.fxml;

    // Exportation des packages pour les rendre accessibles
    exports com.melocode.pigestion;
    exports com.melocode.pigestion.controller;
    exports com.melocode.pigestion.model;
    exports com.melocode.pigestion.service;
    exports com.melocode.pigestion.utils;
}