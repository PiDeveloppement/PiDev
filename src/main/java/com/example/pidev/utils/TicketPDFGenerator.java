package com.example.pidev.utils;

import com.example.pidev.model.event.Event;
import com.example.pidev.model.event.EventTicket;
import com.itextpdf.io.image.ImageDataFactory;
import com.itextpdf.kernel.colors.ColorConstants;
import com.itextpdf.kernel.colors.DeviceRgb;
import com.itextpdf.kernel.pdf.PdfDocument;
import com.itextpdf.kernel.pdf.PdfWriter;
import com.itextpdf.layout.Document;
import com.itextpdf.layout.borders.SolidBorder;
import com.itextpdf.layout.element.Image;
import com.itextpdf.layout.element.Paragraph;
import com.itextpdf.layout.element.Table;
import com.itextpdf.layout.properties.TextAlignment;
import com.itextpdf.layout.properties.UnitValue;

import java.io.File;
import java.io.FileOutputStream;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.time.format.DateTimeFormatter;

/**
 * Générateur de PDF professionnel pour les billets d'événements
 * @author Ons Abdesslem
 */
public class TicketPDFGenerator {

    private static final String PDF_FOLDER = "tickets_pdf";
    private static final DateTimeFormatter DATE_FORMATTER = DateTimeFormatter.ofPattern("dd/MM/yyyy HH:mm");

    /**
     * Génère un PDF de billet professionnel avec QR code
     * @param ticket Le billet
     * @param event L'événement associé
     * @param userName Nom du participant
     * @return Le chemin du fichier PDF généré
     */
    public static String generateTicketPDF(EventTicket ticket, Event event, String userName) {
        try {
            // Créer le dossier s'il n'existe pas
            File folder = new File(PDF_FOLDER);
            if (!folder.exists()) {
                folder.mkdirs();
            }

            // Nom du fichier PDF
            String fileName = PDF_FOLDER + "/ticket_" + ticket.getTicketCode() + ".pdf";
            File pdfFile = new File(fileName);

            // Créer le PDF
            PdfWriter writer = new PdfWriter(new FileOutputStream(pdfFile));
            PdfDocument pdf = new PdfDocument(writer);
            Document document = new Document(pdf);

            // Couleurs personnalisées
            DeviceRgb primaryColor = new DeviceRgb(13, 71, 161); // Bleu EventFlow
            DeviceRgb accentColor = new DeviceRgb(33, 150, 243);
            DeviceRgb lightGray = new DeviceRgb(245, 245, 245);

            // === EN-TÊTE ===
            Paragraph header = new Paragraph("EVENTFLOW")
                .setFontSize(28)
                .setBold()
                .setFontColor(primaryColor)
                .setTextAlignment(TextAlignment.CENTER)
                .setMarginBottom(5);
            document.add(header);

            Paragraph subHeader = new Paragraph("Billet d'Événement")
                .setFontSize(14)
                .setFontColor(ColorConstants.GRAY)
                .setTextAlignment(TextAlignment.CENTER)
                .setMarginBottom(20);
            document.add(subHeader);

            // === TITRE DE L'ÉVÉNEMENT ===
            Paragraph eventTitle = new Paragraph(event.getTitle())
                .setFontSize(22)
                .setBold()
                .setFontColor(primaryColor)
                .setTextAlignment(TextAlignment.CENTER)
                .setMarginBottom(20);
            document.add(eventTitle);

            // === INFORMATIONS DU BILLET ===
            Table infoTable = new Table(UnitValue.createPercentArray(new float[]{1, 2}))
                .setWidth(UnitValue.createPercentValue(100))
                .setMarginBottom(20);

            addInfoRow(infoTable, "📅 Date :", event.getFormattedStartDate());
            addInfoRow(infoTable, "📍 Lieu :", event.getLocation());
            addInfoRow(infoTable, "👤 Participant :", userName);
            addInfoRow(infoTable, "🎫 Code :", ticket.getTicketCode());
            addInfoRow(infoTable, "💰 Prix :", event.getPriceDisplay());

            String status = ticket.isUsed() ? "✓ Utilisé" : "✓ Valide";
            addInfoRow(infoTable, "📊 Statut :", status);

            document.add(infoTable);

            // === QR CODE ===
            Paragraph qrTitle = new Paragraph("Présentez ce QR Code à l'entrée")
                .setFontSize(14)
                .setBold()
                .setTextAlignment(TextAlignment.CENTER)
                .setMarginTop(20)
                .setMarginBottom(10);
            document.add(qrTitle);

            // Générer l'URL du QR code via QuickChart
            String qrUrl = generateQRCodeUrl(ticket.getTicketCode());

            try {
                // Télécharger et intégrer le QR code
                Image qrImage = new Image(ImageDataFactory.create(qrUrl));
                qrImage.setWidth(200);
                qrImage.setHeight(200);
                qrImage.setHorizontalAlignment(com.itextpdf.layout.properties.HorizontalAlignment.CENTER);
                document.add(qrImage);
            } catch (Exception e) {
                System.err.println("⚠️ Impossible de charger le QR code: " + e.getMessage());
                // Fallback : afficher le code en texte
                Paragraph codeText = new Paragraph(ticket.getTicketCode())
                    .setFontSize(16)
                    .setBold()
                    .setTextAlignment(TextAlignment.CENTER)
                    .setFontColor(primaryColor);
                document.add(codeText);
            }

            // === FOOTER ===
            Paragraph footer = new Paragraph("Ce billet est nominatif et valable une seule fois.\nConservez-le précieusement jusqu'au jour de l'événement.")
                .setFontSize(10)
                .setFontColor(ColorConstants.GRAY)
                .setTextAlignment(TextAlignment.CENTER)
                .setMarginTop(30)
                .setItalic();
            document.add(footer);

            Paragraph footerDate = new Paragraph("Généré le : " + java.time.LocalDateTime.now().format(DATE_FORMATTER))
                .setFontSize(8)
                .setFontColor(ColorConstants.LIGHT_GRAY)
                .setTextAlignment(TextAlignment.CENTER)
                .setMarginTop(10);
            document.add(footerDate);

            // Fermer le document
            document.close();

            System.out.println("✅ PDF généré : " + fileName);
            return fileName;

        } catch (Exception e) {
            System.err.println("❌ Erreur génération PDF : " + e.getMessage());
            e.printStackTrace();
            return null;
        }
    }

    /**
     * Ajoute une ligne d'information au tableau
     */
    private static void addInfoRow(Table table, String label, String value) {
        DeviceRgb labelColor = new DeviceRgb(100, 100, 100);
        DeviceRgb valueColor = new DeviceRgb(33, 33, 33);

        table.addCell(new Paragraph(label)
            .setFontSize(12)
            .setBold()
            .setFontColor(labelColor)
            .setPadding(8));

        table.addCell(new Paragraph(value)
            .setFontSize(12)
            .setFontColor(valueColor)
            .setPadding(8));
    }

    /**
     * Génère l'URL du QR code via QuickChart
     */
    private static String generateQRCodeUrl(String ticketCode) {
        try {
            // URL de validation (à adapter selon ton environnement)
            String validationUrl = "http://localhost:8080/validate?code=" +
                                  URLEncoder.encode(ticketCode, StandardCharsets.UTF_8);

            // Générer le QR via QuickChart
            String qrUrl = "https://quickchart.io/qr?text=" +
                          URLEncoder.encode(validationUrl, StandardCharsets.UTF_8) +
                          "&size=200&margin=2";

            return qrUrl;
        } catch (Exception e) {
            System.err.println("❌ Erreur génération URL QR: " + e.getMessage());
            return null;
        }
    }

    /**
     * Ouvre le PDF généré
     */
    public static void openPDF(String pdfPath) {
        try {
            File pdfFile = new File(pdfPath);
            if (pdfFile.exists()) {
                if (System.getProperty("os.name").toLowerCase().contains("win")) {
                    Runtime.getRuntime().exec("rundll32 url.dll,FileProtocolHandler " + pdfFile.getAbsolutePath());
                } else if (System.getProperty("os.name").toLowerCase().contains("mac")) {
                    Runtime.getRuntime().exec("open " + pdfFile.getAbsolutePath());
                } else {
                    Runtime.getRuntime().exec("xdg-open " + pdfFile.getAbsolutePath());
                }
                System.out.println("📄 PDF ouvert : " + pdfPath);
            } else {
                System.err.println("❌ Fichier PDF introuvable : " + pdfPath);
            }
        } catch (Exception e) {
            System.err.println("❌ Erreur ouverture PDF : " + e.getMessage());
            e.printStackTrace();
        }
    }
}

