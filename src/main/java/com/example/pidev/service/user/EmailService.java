package com.example.pidev.service.user;

import java.util.Properties;
import javax.mail.*;
import javax.mail.internet.*;

public class EmailService {

    // Configuration pour MailDev (local - AUCUN MOT DE PASSE REQUIS)
    private static final String SMTP_HOST = "localhost";
    private static final String SMTP_PORT = "1025";
    private static final String FROM_EMAIL = "noreply@eventflow.com";

    public static void sendResetPasswordEmail(String toEmail, String userName, String token) {
        Properties props = new Properties();
        props.put("mail.smtp.host", SMTP_HOST);
        props.put("mail.smtp.port", SMTP_PORT);
        props.put("mail.debug", "true"); // Pour voir les logs

        // Plus besoin d'authentification !
        Session session = Session.getInstance(props);
        session.setDebug(true);

        try {
            Message message = new MimeMessage(session);
            message.setFrom(new InternetAddress(FROM_EMAIL));
            message.setRecipients(Message.RecipientType.TO, InternetAddress.parse(toEmail));
            message.setSubject("🔐 Réinitialisation de votre mot de passe - EventFlow");

            String htmlContent = "<!DOCTYPE html>" +
                    "<html>" +
                    "<body>" +
                    "<h2>Réinitialisation de mot de passe</h2>" +
                    "<p>Bonjour <strong>" + userName + "</strong>,</p>" +
                    "<p>Votre code de réinitialisation : <strong>" + token + "</strong></p>" +
                    "<p>Ce code expirera dans 1 heure.</p>" +
                    "</body>" +
                    "</html>";

            message.setContent(htmlContent, "text/html; charset=utf-8");

            System.out.println("📧 Envoi d'email à: " + toEmail);
            Transport.send(message);
            System.out.println("✅ Email envoyé! Voir sur http://localhost:1080");

        } catch (MessagingException e) {
            e.printStackTrace();
            throw new RuntimeException("Erreur: " + e.getMessage());
        }
    }
}