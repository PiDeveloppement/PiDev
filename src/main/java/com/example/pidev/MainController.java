package com.example.pidev;

import com.example.pidev.controller.sponsor.SponsorAdminController;
import com.example.pidev.controller.sponsor.SponsorPortalController;
import com.example.pidev.service.translation.TranslationService;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.image.ImageView;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import javafx.scene.text.Text;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.function.Consumer;

public class MainController {

    private static MainController instance;
    public static MainController getInstance() { return instance; }

    private String lastSponsorPortalEmail;
    public void setLastSponsorPortalEmail(String email) { this.lastSponsorPortalEmail = email; }
    public String getLastSponsorPortalEmail() { return lastSponsorPortalEmail; }

    private String currentPage; // pour recharger après changement de langue

    private static final String SPONSOR_PORTAL_FXML = "/com/example/pidev/fxml/Sponsor/sponsor_portal.fxml";
    private static final String SPONSOR_ADMIN_FXML  = "/com/example/pidev/fxml/Sponsor/sponsor_admin.fxml";
    private static final String BUDGET_LIST_FXML    = "/com/example/pidev/fxml/Budget/budget.fxml";
    private static final String DEPENSE_LIST_FXML   = "/com/example/pidev/fxml/Depense/depense-modern.fxml";
    private static final String DASHBOARD_FXML      = "/com/example/pidev/fxml/dashboard/dashboard.fxml";

    @FXML private VBox pageContentContainer;

    // Top bar
    @FXML private Label navDateLabel;
    @FXML private Label navTimeLabel;
    @FXML private Label userNameLabel;
    @FXML private Label userRoleLabel;
    @FXML private Text userInitialsText;
    @FXML private ImageView profileImageView;
    @FXML private StackPane initialsContainer;
    @FXML private StackPane avatarContainer;
    @FXML private MenuButton profileMenu;
    @FXML private ComboBox<String> languageCombo;

    // Sidebar buttons
    @FXML private Button dashboardBtn;
    @FXML private Button eventsToggleBtn;
    @FXML private VBox eventsSubmenu;
    @FXML private Text eventsArrow;
    @FXML private Button eventsListBtn;
    @FXML private Button categoriesBtn;
    @FXML private Button ticketsBtn;
    @FXML private Button usersToggleBtn;
    @FXML private VBox usersSubmenu;
    @FXML private Text usersArrow;
    @FXML private Button rolesBtn;
    @FXML private Button inscriptionsBtn;
    @FXML private Button sponsorsBtn;
    @FXML private VBox sponsorsSubmenu;
    @FXML private Text sponsorsArrow;
    @FXML private Button sponsorsListBtn;
    @FXML private Button sponsorPortalBtn;
    @FXML private Button budgetBtn;
    @FXML private Button contratsBtn;
    @FXML private Button resourcesToggleBtn;
    @FXML private VBox resourcesSubmenu;
    @FXML private Text resourcesArrow;
    @FXML private Button sallesBtn;
    @FXML private Button equipementsBtn;
    @FXML private Button reservationsBtn;
    @FXML private Button questionnairesToggleBtn;
    @FXML private VBox questionnairesSubmenu;
    @FXML private Text questionnairesArrow;
    @FXML private Button questionsBtn;
    @FXML private Button reponsesBtn;
    @FXML private Button settingsBtn;
    @FXML private Button logoutBtn;

    @FXML
    public void initialize() {
        instance = this;

        configureDateTime();
        loadSessionUserInHeader();

        // Initialiser le sélecteur de langue
        languageCombo.getItems().setAll("Français", "English", "العربية");
        languageCombo.setValue("Français");
        languageCombo.valueProperty().addListener((obs, old, n) -> {
            String lang = "fr";
            if ("English".equals(n)) lang = "en";
            else if ("العربية".equals(n)) lang = "ar";
            TranslationService.setCurrentLang(lang);
            refreshCurrentPage();
        });

        // Page par défaut : Admin Sponsors
        showSponsorsAdmin();
    }

    private void refreshCurrentPage() {
        if (currentPage == null) return;
        switch (currentPage) {
            case "sponsorsAdmin":
                showSponsorsAdmin();
                break;
            case "sponsorPortal":
                showSponsorPortal(lastSponsorPortalEmail);
                break;
            case "budget":
                onBudget();
                break;
            case "depenses":
                onDepenses();
                break;
            // ... autres pages
        }
    }

    private void loadSessionUserInHeader() {
        String fullName = "Maryem Manai";
        String role = "Sponsor";
        String initials = "MM";

        if (userNameLabel != null) userNameLabel.setText(fullName);
        if (userRoleLabel != null) userRoleLabel.setText(role);
        if (userInitialsText != null) userInitialsText.setText(initials);
    }

    private void configureDateTime() {
        DateTimeFormatter dateFmt = DateTimeFormatter.ofPattern("EEEE dd MMMM yyyy");
        DateTimeFormatter timeFmt = DateTimeFormatter.ofPattern("HH:mm:ss");

        LocalDateTime now = LocalDateTime.now();
        if (navDateLabel != null) navDateLabel.setText(now.format(dateFmt));
        if (navTimeLabel != null) navTimeLabel.setText(now.format(timeFmt));

        Thread t = new Thread(() -> {
            while (!Thread.currentThread().isInterrupted()) {
                try {
                    Thread.sleep(1000);
                    Platform.runLater(() -> {
                        LocalDateTime n = LocalDateTime.now();
                        if (navTimeLabel != null) navTimeLabel.setText(n.format(timeFmt));
                        if (navDateLabel != null) navDateLabel.setText(n.format(dateFmt));
                    });
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                }
            }
        });
        t.setDaemon(true);
        t.start();
    }

    @FXML private void toggleEvents()         { toggleSubmenu(eventsSubmenu, eventsArrow, eventsToggleBtn); }
    @FXML private void toggleUsers()          { toggleSubmenu(usersSubmenu, usersArrow, usersToggleBtn); }
    @FXML private void toggleSponsors()       { toggleSubmenu(sponsorsSubmenu, sponsorsArrow, sponsorsBtn); }
    @FXML private void toggleResources()      { toggleSubmenu(resourcesSubmenu, resourcesArrow, resourcesToggleBtn); }
    @FXML private void toggleQuestionnaires() { toggleSubmenu(questionnairesSubmenu, questionnairesArrow, questionnairesToggleBtn); }

    private void toggleSubmenu(VBox submenu, Text arrow, Button active) {
        if (submenu == null) return;
        boolean isVisible = submenu.isVisible();
        submenu.setVisible(!isVisible);
        submenu.setManaged(!isVisible);
        if (arrow != null) arrow.setText(!isVisible ? "▼" : "▶");
        setActiveButton(active);
    }

    private void openSponsorsSubmenu() {
        if (sponsorsSubmenu != null) {
            sponsorsSubmenu.setVisible(true);
            sponsorsSubmenu.setManaged(true);
        }
        if (sponsorsArrow != null) sponsorsArrow.setText("▼");
    }

    @FXML
    public void onDashboard() {
        currentPage = "dashboard";
        setActiveButton(dashboardBtn);
        loadIntoCenter(DASHBOARD_FXML, null);
    }

    @FXML public void onEventsList()   { currentPage = "events"; setActiveButton(eventsListBtn);   showEmptyPage("Événements", "Page non branchée pour le moment."); }
    @FXML public void onCategories()   { currentPage = "categories"; setActiveButton(categoriesBtn);   showEmptyPage("Catégories", "Page non branchée pour le moment."); }
    @FXML public void onTickets()      { currentPage = "tickets"; setActiveButton(ticketsBtn);      showEmptyPage("Billets", "Page non branchée pour le moment."); }
    @FXML public void onRoles()        { currentPage = "roles"; setActiveButton(rolesBtn);        showEmptyPage("Rôles", "Page non branchée pour le moment."); }
    @FXML public void onInscriptions() { currentPage = "inscriptions"; setActiveButton(inscriptionsBtn); showEmptyPage("Inscriptions", "Page non branchée pour le moment."); }

    @FXML
    public void onSponsorsList() {
        showSponsorsAdmin();
    }

    @FXML
    public void onSponsorPortal() {
        openSponsorsSubmenu();
        setActiveButton(sponsorPortalBtn);
        showSponsorPortal(lastSponsorPortalEmail);
    }

    @FXML
    public void onBudget() {
        currentPage = "budget";
        openSponsorsSubmenu();
        setActiveButton(budgetBtn);
        loadIntoCenter(BUDGET_LIST_FXML, null);
    }

    @FXML
    public void onDepenses() {
        currentPage = "depenses";
        openSponsorsSubmenu();
        setActiveButton(contratsBtn);
        loadIntoCenter(DEPENSE_LIST_FXML, null);
    }

    @FXML public void onSalles()       { currentPage = "salles"; setActiveButton(sallesBtn);       showEmptyPage("Salles", "Page non branchée pour le moment."); }
    @FXML public void onEquipements()  { currentPage = "equipements"; setActiveButton(equipementsBtn);  showEmptyPage("Équipements", "Page non branchée pour le moment."); }
    @FXML public void onReservations() { currentPage = "reservations"; setActiveButton(reservationsBtn); showEmptyPage("Réservations", "Page non branchée pour le moment."); }
    @FXML public void onQuestions()    { currentPage = "questions"; setActiveButton(questionsBtn);    showEmptyPage("Questions", "Page non branchée pour le moment."); }
    @FXML public void onReponses()     { currentPage = "reponses"; setActiveButton(reponsesBtn);     showEmptyPage("Réponses", "Page non branchée pour le moment."); }
    @FXML public void showSettings()   { currentPage = "settings"; setActiveButton(settingsBtn);     showEmptyPage("Paramètres", "Page non branchée pour le moment."); }
    @FXML public void showProfile()    { showEmptyPage("Profil", "Page non branchée pour le moment."); }

    @FXML
    public void logout() {
        System.out.println("Déconnexion...");
    }

    public void showSponsorPortal(String email) {
        currentPage = "sponsorPortal";
        openSponsorsSubmenu();
        setActiveButton(sponsorPortalBtn);
        if (email != null && !email.isBlank()) setLastSponsorPortalEmail(email);
        loadIntoCenter(SPONSOR_PORTAL_FXML, (SponsorPortalController ctrl) -> {
            String e = getLastSponsorPortalEmail();
            if (e != null && !e.isBlank()) ctrl.setInitialEmail(e);
        });
    }

    public void showSponsorsAdmin() {
        currentPage = "sponsorsAdmin";
        openSponsorsSubmenu();
        setActiveButton(sponsorsListBtn);
        loadIntoCenter(SPONSOR_ADMIN_FXML, (SponsorAdminController ctrl) -> {});
    }

    public void showSponsors() { showSponsorsAdmin(); }
    public void showBudget()   { onBudget(); }
    public void showDepenses() { onDepenses(); }

    public <T> void loadIntoCenter(String fxmlPath, Consumer<T> controllerConsumer) {
        try {
            if (pageContentContainer == null) {
                throw new IllegalStateException("pageContentContainer est null. Vérifiez fx:id dans MainLayout.fxml");
            }

            FXMLLoader loader = new FXMLLoader(getClass().getResource(fxmlPath));
            Parent page = loader.load();
            pageContentContainer.getChildren().setAll(page);

            if (controllerConsumer != null) {
                @SuppressWarnings("unchecked")
                T ctrl = (T) loader.getController();
                controllerConsumer.accept(ctrl);
            }

        } catch (Exception e) {
            e.printStackTrace();
            showEmptyPage("Erreur", "Impossible de charger : " + fxmlPath + "\n" + e.getMessage());
        }
    }

    private void showEmptyPage(String title, String subtitle) {
        if (pageContentContainer == null) return;

        VBox box = new VBox(10);
        box.setAlignment(Pos.TOP_LEFT);
        box.setPadding(new Insets(24));
        box.setStyle("""
                -fx-background-color: white;
                -fx-background-radius: 14;
                -fx-border-radius: 14;
                -fx-border-color: #e2e8f0;
                -fx-border-width: 1;
                """);

        Label t = new Label(title);
        t.setStyle("-fx-font-size: 22px; -fx-font-weight: 800; -fx-text-fill: #0f172a;");

        Label s = new Label(subtitle);
        s.setStyle("-fx-font-size: 14px; -fx-text-fill: #64748b;");

        box.getChildren().addAll(t, s);
        pageContentContainer.getChildren().setAll(box);
    }

    private void setActiveButton(Button button) {
        Button[] all = {
                dashboardBtn,
                eventsToggleBtn, eventsListBtn, categoriesBtn, ticketsBtn,
                usersToggleBtn, rolesBtn, inscriptionsBtn,
                sponsorsBtn, sponsorsListBtn, sponsorPortalBtn, budgetBtn, contratsBtn,
                resourcesToggleBtn, sallesBtn, equipementsBtn, reservationsBtn,
                questionnairesToggleBtn, questionsBtn, reponsesBtn,
                settingsBtn
        };

        for (Button b : all) {
            if (b == null) continue;

            b.getStyleClass().remove("sidebar-button-active");

            boolean isSub = b == eventsListBtn || b == categoriesBtn || b == ticketsBtn ||
                    b == rolesBtn || b == inscriptionsBtn ||
                    b == sponsorsListBtn || b == sponsorPortalBtn || b == budgetBtn || b == contratsBtn ||
                    b == sallesBtn || b == equipementsBtn || b == reservationsBtn ||
                    b == questionsBtn || b == reponsesBtn;

            b.getStyleClass().removeAll("main-menu-button", "submenu-button");
            b.getStyleClass().add(isSub ? "submenu-button" : "main-menu-button");
        }

        if (button != null) button.getStyleClass().add("sidebar-button-active");
    }
}