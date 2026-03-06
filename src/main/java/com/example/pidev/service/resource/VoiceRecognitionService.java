package com.example.pidev.service.resource;

import org.json.JSONObject;
import org.vosk.Model;
import org.vosk.Recognizer;

import javax.sound.sampled.AudioFormat;
import javax.sound.sampled.AudioSystem;
import javax.sound.sampled.DataLine;
import javax.sound.sampled.TargetDataLine;
import java.io.File;

public class VoiceRecognitionService extends Thread {

    private static final Object MODEL_LOCK = new Object();
    private static volatile Model sharedModel;

    private final VoiceCommandListener listener;
    private volatile boolean running = true;
    private volatile TargetDataLine activeLine;

    public interface VoiceCommandListener {
        void onCommandDetected(String jsonResult);
    }

    public VoiceRecognitionService(VoiceCommandListener listener) {
        this.listener = listener;
        setName("voice-recognition-service");
        setDaemon(true);
    }

    @Override
    public void run() {
        Recognizer recognizer = null;
        TargetDataLine line = null;

        try {
            Model model = getOrLoadModel();
            if (model == null) {
                System.err.println("Voice model not available.");
                return;
            }

            recognizer = new Recognizer(model, 16000.0f);

            AudioFormat format = new AudioFormat(16000, 16, 1, true, false);
            DataLine.Info info = new DataLine.Info(TargetDataLine.class, format);
            line = (TargetDataLine) AudioSystem.getLine(info);
            line.open(format);
            line.start();
            activeLine = line;

            byte[] buffer = new byte[2048];
            String lastPartial = "";
            long lastPartialEmitMs = 0L;

            while (running) {
                int bytesRead = line.read(buffer, 0, buffer.length);
                if (bytesRead <= 0) {
                    continue;
                }

                if (recognizer.acceptWaveForm(buffer, bytesRead)) {
                    String finalJson = recognizer.getResult();
                    String text = extractText(finalJson, "text");
                    if (!text.isBlank()) {
                        listener.onCommandDetected(finalJson);
                    }
                    lastPartial = "";
                    continue;
                }

                String partialJson = recognizer.getPartialResult();
                String partial = extractText(partialJson, "partial");
                long now = System.currentTimeMillis();

                if (partial.length() >= 4
                        && !partial.equals(lastPartial)
                        && now - lastPartialEmitMs >= 250) {
                    lastPartial = partial;
                    lastPartialEmitMs = now;
                    listener.onCommandDetected(new JSONObject().put("text", partial).toString());
                }
            }
        } catch (Exception e) {
            System.err.println("Voice service error: " + e.getMessage());
            e.printStackTrace();
        } finally {
            activeLine = null;
            if (line != null) {
                try {
                    line.stop();
                } catch (Exception ignored) {}
                try {
                    line.close();
                } catch (Exception ignored) {}
            }
            if (recognizer != null) {
                recognizer.close();
            }
        }
    }

    public void stopListening() {
        running = false;
        TargetDataLine line = activeLine;
        if (line != null) {
            try {
                line.stop();
            } catch (Exception ignored) {}
            try {
                line.close();
            } catch (Exception ignored) {}
        }
    }

    private static Model getOrLoadModel() {
        if (sharedModel != null) {
            return sharedModel;
        }

        synchronized (MODEL_LOCK) {
            if (sharedModel != null) {
                return sharedModel;
            }

            String modelPath = resolveModelPath();
            if (modelPath == null) {
                return null;
            }

            try {
                sharedModel = new Model(modelPath);
                System.out.println("Voice model loaded: " + modelPath);
            } catch (Exception e) {
                System.err.println("Unable to load Vosk model: " + e.getMessage());
                sharedModel = null;
            }

            return sharedModel;
        }
    }

    private static String resolveModelPath() {
        String base = System.getProperty("user.dir");
        String[] candidates = new String[] {
                base + File.separator + "src" + File.separator + "main" + File.separator + "java"
                        + File.separator + "com" + File.separator + "example" + File.separator + "pidev"
                        + File.separator + "model" + File.separator + "resource" + File.separator + "fr",
                base + File.separator + "src" + File.separator + "main" + File.separator + "resources"
                        + File.separator + "vosk" + File.separator + "fr",
                base + File.separator + "models" + File.separator + "vosk" + File.separator + "fr"
        };

        for (String candidate : candidates) {
            File dir = new File(candidate);
            if (dir.exists() && dir.isDirectory()) {
                return dir.getAbsolutePath();
            }
        }
        return null;
    }

    private String extractText(String json, String key) {
        try {
            return new JSONObject(json).optString(key, "");
        } catch (Exception e) {
            return "";
        }
    }
}
