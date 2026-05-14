# Google AI Application

## Prerequisites
1. Kindly make sure you have ADK installed and loggedin for local development for this module
   1. Add in your web-build for .ddev
      1. ```
        # Install gcloud SDK
        RUN curl -sSL https://sdk.cloud.google.com > /tmp/gcl && \
            bash /tmp/gcl --install-dir=/usr/local/gcloud --disable-prompts

        # Add gcloud to PATH
        ENV PATH $PATH:/usr/local/gcloud/google-cloud-sdk/bin
      ```
   2. ddev exec gcloud auth application-default login
   3. ddev exec gcloud auth application-default set-quota-project YOUR_PROJECT_ID
   4. please wait for deletion to complete before recreating with the same ID. The deletion could take a couple of hours.


