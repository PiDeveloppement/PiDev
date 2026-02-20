module com.example.pidev {
    // JavaFX
    requires javafx.controls;
    requires javafx.fxml;

    // Bibliothèques
    requires atlantafx.base;
    requires jfxtras.controls;
    requires jfxtras.common;

    // Base de données
    requires java.sql;
    requires mysql.connector.j;           // ← AJOUT ARIJ

    // JPA et autres
    requires jakarta.persistence;          // ← AJOUT ARIJ
    requires java.prefs;                   // ← AJOUT ARIJ

    // iText PDF
    requires kernel;
    requires layout;

    // ========== EXPORTS ==========

    exports com.example.pidev;
    exports com.example.pidev.model.event;
    exports com.example.pidev.model.sponsor;
    exports com.example.pidev.model.user;      // ← AJOUT ARIJ
    exports com.example.pidev.model.role;      // ← AJOUT ARIJ
    exports com.example.pidev.controller.event;
    exports com.example.pidev.controller.auth;      // ← AJOUT ARIJ
    exports com.example.pidev.controller.user;      // ← AJOUT ARIJ
    exports com.example.pidev.controller.role;      // ← AJOUT ARIJ
    exports com.example.pidev.controller.sponsor;
    exports com.example.pidev.controller.front;     // ← AJOUT FRONT OFFICE
    exports com.example.pidev.utils;

    // ========== OPENS ==========

    opens com.example.pidev to javafx.fxml;
    opens com.example.pidev.controller.dashboard to javafx.fxml;
    opens com.example.pidev.controller.event to javafx.fxml;
    opens com.example.pidev.controller.sponsor to javafx.fxml;
    opens com.example.pidev.controller.auth to javafx.fxml;      // ← AJOUT ARIJ
    opens com.example.pidev.controller.user to javafx.fxml;      // ← AJOUT ARIJ
    opens com.example.pidev.controller.role to javafx.fxml;      // ← AJOUT ARIJ
    opens com.example.pidev.controller.front to javafx.fxml;     // ← AJOUT FRONT OFFICE
    opens com.example.pidev.utils to javafx.fxml;
}