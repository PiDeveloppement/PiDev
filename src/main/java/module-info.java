<<<<<<< HEAD
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
=======
module com.example.pidev {
    requires javafx.controls;
    requires org.slf4j;
    requires javafx.fxml;
    requires atlantafx.base;
    requires java.sql;
    requires mysql.connector.j;
    requires jakarta.persistence;
    requires java.prefs;
    requires javafx.web;
    requires java.desktop;
    requires java.net.http;
    requires kernel;
    requires layout;
    requires itextpdf;
    requires java.mail;
    requires org.apache.pdfbox;


    // Exportez tous les packages nécessaires
    exports com.example.pidev;
    exports com.example.pidev.model.event;
    exports com.example.pidev.model.resource;
    exports com.example.pidev.model.user;
    exports com.example.pidev.model.role;
    exports com.example.pidev.model.sponsor;
    exports com.example.pidev.controller.event;
    exports com.example.pidev.controller.sponsor;
    exports com.example.pidev.controller.auth;
    exports com.example.pidev.controller.user;
    exports com.example.pidev.controller.role;
    exports com.example.pidev.controller.questionnaire;
    exports com.example.pidev.service.user;
    // Ouvrez tous les packages à javafx.fxml

    opens com.example.pidev to javafx.fxml;
    opens com.example.pidev.controller.dashboard to javafx.fxml;
    opens com.example.pidev.controller.event to javafx.fxml;
    opens com.example.pidev.controller.auth to javafx.fxml;
    opens com.example.pidev.controller.user to javafx.fxml;
    opens com.example.pidev.controller.role to javafx.fxml;
    opens com.example.pidev.controller.resource to javafx.fxml;
    opens com.example.pidev.controller.questionnaire to javafx.fxml;
    opens com.example.pidev.controller.sponsor to javafx.fxml;
    opens com.example.pidev.controller.budget to javafx.fxml;
    opens com.example.pidev.controller.depense to javafx.fxml;
    opens com.example.pidev.service.user to javafx.fxml;
>>>>>>> 467a8f98ef9c9645a7a7df9c10bf5e2b8a572c9a
}