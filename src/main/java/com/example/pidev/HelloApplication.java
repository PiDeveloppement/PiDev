package com.example.pidev;

import atlantafx.base.theme.PrimerLight;
import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.stage.Stage;
import javafx.stage.StageStyle;
import java.net.URL;

public class HelloApplication extends Application {

    private static Stage primaryStage;

    @Override
    public void start(Stage stage) throws Exception {
        primaryStage = stage;

        // Appliquer AtlantaFX
        Application.setUserAgentStylesheet(new PrimerLight().getUserAgentStylesheet());

        // LE CHEMIN EXACT d'après votre capture d'écran :
        URL fxmlLocation = getClass().getResource("/com/example/pidev/fxml/resource/main_layout.fxml");

        if (fxmlLocation == null) {
            throw new RuntimeException("❌ Fichier FXML introuvable ! Vérifiez le chemin dans resources.");
        }

        Parent root = FXMLLoader.load(fxmlLocation);

        stage.initStyle(StageStyle.DECORATED);

        // Taille de la fenêtre
        Scene scene = new Scene(root, 1300, 850);

        // Chemin du CSS d'après votre capture :
        URL cssResource = getClass().getResource("/com/example/pidev/css/atlantafx-custom.css");
        if (cssResource != null) {
            scene.getStylesheets().add(cssResource.toExternalForm());
        }

        stage.setTitle("EventFlow - Gestion Événements");
        stage.setScene(scene);
        stage.setMinWidth(1100);
        stage.setMinHeight(800);
        stage.show();
    }

    public static void main(String[] args) {
        launch(args);
    }
}
